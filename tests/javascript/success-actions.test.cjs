const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const vm = require('node:vm');

function checkout(
    options = {},
    ajax = {},
    createContent = () => ({dataset: {}, innerHTML: '', textContent: ''}),
    SanitizerClass
) {
    const children = [];
    const scheduled = new Map();
    const navigations = [];
    const events = [];
    let definition;
    let timerId = 0;
    const element = {
        isConnected: true,
        querySelector: () => ({appendChild: child => children.push(child)})
    };
    const context = {
        URL,
        window: {
            Sanitizer: SanitizerClass,
            location: {href: 'https://shop.example/checkout', hash: '', assign: url => navigations.push(url)},
            setTimeout: (callback, delay) => {
                scheduled.set(++timerId, {callback, delay});
                return timerId;
            },
            clearTimeout: id => scheduled.delete(id)
        },
        document: {createElement: createContent},
        Class: function (value) { return value; },
        define: (name, dependencies, factory) => {
            definition = factory({addEvent() {}}, {}, {}, {}, {}, ajax);
        }
    };
    vm.runInNewContext(
        readFileSync(`${__dirname}/../../bin/frontend/controls/SimpleCheckout.js`, 'utf8'),
        context
    );
    const attributes = {...definition.options, ...options};
    const handlers = {};
    const instance = {
        ...definition,
        parent() {},
        getAttribute: name => attributes[name],
        setAttribute: (name, value) => { attributes[name] = value; },
        getElm: () => element,
        addEvents: values => Object.assign(handlers, values),
        fireEvent: name => {
            events.push(name);
        },
        $removeProcessPaymentChange() {}
    };
    instance.initialize(options);
    return {instance, attributes, handlers, children, scheduled, navigations, events, element};
}

function finish(instance) {
    instance.$showOrderSuccessInfoPending = true;
    instance.$successViewReady = true;
    instance.$finishSuccessInfo('Finish');
}

const settings = {
    successContent: '<a class="button" href="/konto">Mein Konto</a>',
    successUrl: '/konto',
    successRedirectDelay: 5,
    successContentSource: 'php'
};

test('defaults do not add content or redirect', () => {
    const state = checkout();
    state.instance.$applySuccessSettings({successContent: false, successUrl: false, successRedirectDelay: false});
    finish(state.instance);
    assert.equal(state.children.length, 0);
    assert.equal(state.scheduled.size, 0);
});

test('actions wait for the visible Finish step and run only once', () => {
    const state = checkout();
    state.instance.$applySuccessSettings(settings);
    state.instance.$showOrderSuccessInfoPending = true;
    state.instance.$finishSuccessInfo('Finish');
    state.instance.$successViewReady = true;
    state.instance.$finishSuccessInfo('Processing');
    assert.equal(state.children.length, 0);
    assert.equal(state.scheduled.size, 0);
    state.instance.$finishSuccessInfo('Finish');
    state.instance.$finishSuccessInfo('Finish');
    assert.equal(state.children.length, 1);
    assert.equal(state.children[0].innerHTML, settings.successContent);
    assert.equal(state.children[0].dataset.name, 'success-content');
    assert.deepEqual(state.events, ['showOrderSuccessInfo']);
    assert.equal(state.scheduled.size, 1);
    const timer = [...state.scheduled.values()][0];
    assert.equal(timer.delay, 5000);
    timer.callback();
    assert.deepEqual(state.navigations, ['https://shop.example/konto']);
});

test('asynchronous embedded process completion also shows the actions', () => {
    const state = checkout();
    state.instance.$applySuccessSettings(settings);
    state.instance.$showOrderSuccessInfoPending = true;
    state.instance.$successViewReady = true;
    let step = 'Processing';
    state.instance.$EmbeddedOrderProcess = {getAttribute: () => step};
    state.instance.$maybeFireShowOrderSuccessInfo();
    assert.equal(state.children.length, 0);
    step = 'Finish';
    state.instance.$maybeFireShowOrderSuccessInfo();
    assert.equal(state.children.length, 1);
});

test('content can be shown without a redirect', () => {
    const state = checkout();
    state.instance.$applySuccessSettings({...settings, successRedirectDelay: false});
    finish(state.instance);
    assert.equal(state.children.length, 1);
    assert.equal(state.scheduled.size, 0);
});

test('redirect works independently of content and permits zero seconds', () => {
    const state = checkout();
    state.instance.$applySuccessSettings({...settings, successContent: false, successRedirectDelay: 0});
    finish(state.instance);
    assert.equal(state.children.length, 0);
    assert.equal([...state.scheduled.values()][0].delay, 0);
});

test('unconfirmed client HTML is never inserted', () => {
    const state = checkout({successContent: '<img onerror="alert(1)">'});
    finish(state.instance);
    assert.equal(state.children.length, 0);
});

test('disabled success information never starts completion actions', () => {
    const state = checkout({showOrderSuccessInfo: false});
    state.instance.$applySuccessSettings(settings);
    state.instance.$successViewReady = true;
    state.instance.$finishSuccessInfo('Finish');
    assert.equal(state.children.length, 0);
    assert.equal(state.scheduled.size, 0);
});

test('destroying the checkout cancels a pending redirect', () => {
    const state = checkout();
    state.instance.$applySuccessSettings(settings);
    finish(state.instance);
    state.handlers.onDestroy.call(state.instance);
    assert.equal(state.scheduled.size, 0);
});

test('a removed checkout cannot redirect the browser', () => {
    const state = checkout();
    state.instance.$applySuccessSettings(settings);
    finish(state.instance);
    state.element.isConnected = false;
    [...state.scheduled.values()][0].callback();
    assert.deepEqual(state.navigations, []);
});

test('script URLs and invalid delays never start redirects', () => {
    for (const invalid of [
        {successUrl: 'javascript:alert(1)'},
        {successRedirectDelay: -1},
        {successRedirectDelay: '5'},
        {successRedirectDelay: 86401}
    ]) {
        const state = checkout();
        state.instance.$applySuccessSettings({...settings, ...invalid});
        finish(state.instance);
        assert.equal(state.scheduled.size, 0);
    }
});

test('order loading sends configuration and adopts the server settings on return', async () => {
    let request;
    const state = checkout({orderHash: 'order-hash', ...settings}, {
        post: (name, callback, params) => {
            request = params;
            callback({order: {}, successSettings: {...settings, successContent: 'Configured in PHP'}});
        }
    });
    await state.instance.$loadOrder();
    assert.deepEqual(JSON.parse(request.successSettings), {
        successContent: settings.successContent,
        successUrl: settings.successUrl,
        successRedirectDelay: settings.successRedirectDelay
    });
    finish(state.instance);
    assert.equal(state.children[0].innerHTML, 'Configured in PHP');
});

test('server-rendered JSON settings support a reload without client options', () => {
    const state = checkout();
    state.instance.$applySuccessSettings(JSON.stringify(settings));
    finish(state.instance);
    assert.equal(state.children[0].innerHTML, settings.successContent);
    assert.equal([...state.scheduled.values()][0].delay, 5000);
});

test('frontend content is passed to the native sanitizer without writing innerHTML', () => {
    const calls = [];
    const state = checkout({}, {}, () => ({
        dataset: {},
        setHTML: html => calls.push(html),
        set innerHTML(value) { throw new Error('Untrusted HTML must never use innerHTML'); },
        set textContent(value) { throw new Error('Native sanitization is available'); }
    }));
    const content = '<a href="/konto">Konto</a><img src=x onerror="alert(1)"><script>alert(2)</script>';
    state.instance.$applySuccessSettings({...settings, successContent: content, successContentSource: 'frontend'});
    finish(state.instance);
    assert.deepEqual(calls, [content]);
    assert.equal(state.children.length, 1);
});

test('browsers without setHTML use textContent for frontend markup', () => {
    const content = '<img src=x onerror="alert(1)"><script>alert(2)</script>';
    const state = checkout({}, {}, () => ({
        dataset: {},
        textContent: '',
        set innerHTML(value) { throw new Error('Unsafe fallback'); }
    }));
    state.instance.$applySuccessSettings({...settings, successContent: content, successContentSource: 'frontend'});
    finish(state.instance);
    assert.equal(state.children[0].textContent, content);
});

test('native default sanitization also allows CSS classes and accessibility labels', () => {
    const attributes = [];
    let suppliedSanitizer;
    class NativeSanitizer {
        allowAttribute(name) { attributes.push(name); }
    }
    const state = checkout({}, {}, () => ({
        dataset: {},
        setHTML: (html, options) => { suppliedSanitizer = options.sanitizer; }
    }), NativeSanitizer);
    state.instance.$applySuccessSettings({...settings, successContentSource: 'frontend'});
    finish(state.instance);
    assert.ok(suppliedSanitizer instanceof NativeSanitizer);
    assert.deepEqual(attributes, ['class', 'aria-label', 'aria-hidden']);
});

test('a missing or unknown source marker always uses safe rendering', () => {
    for (const source of [undefined, null, '', true, 'unknown']) {
        const state = checkout();
        state.instance.$applySuccessSettings({...settings, successContentSource: source});
        finish(state.instance);
        assert.equal(state.children[0].textContent, settings.successContent);
        assert.equal(state.children[0].innerHTML, '');
    }
});

test('returning from payment keeps frontend content subject to sanitization', async () => {
    const state = checkout({orderHash: 'order-hash'}, {
        post: (name, callback) => callback({
            order: {},
            successSettings: {...settings, successContentSource: 'frontend'}
        })
    });
    await state.instance.$loadOrder();
    finish(state.instance);
    assert.equal(state.children[0].textContent, settings.successContent);
    assert.equal(state.children[0].innerHTML, '');
    assert.equal(state.instance.$getRequestedSuccessSettings().successContent, false);
});
