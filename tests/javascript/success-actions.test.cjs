const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const vm = require('node:vm');

function checkout(options = {}, ajax = {}) {
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
            location: {href: 'https://shop.example/checkout', hash: '', assign: url => navigations.push(url)},
            setTimeout: (callback, delay) => {
                scheduled.set(++timerId, {callback, delay});
                return timerId;
            },
            clearTimeout: id => scheduled.delete(id)
        },
        document: {createElement: () => ({dataset: {}, innerHTML: ''})},
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
    successRedirectDelay: 5
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
