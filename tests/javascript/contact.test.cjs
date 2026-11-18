const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const vm = require('node:vm');

function contact() {
    const handlers = new Map();
    const attributes = new Map();
    const classes = new Set();
    let updates = 0;
    let focused = false;
    let loginOptions;
    let opened = false;
    const email = {
        validationMessage: 'Please enter an email address.',
        addEventListener: (name, callback) => handlers.set(`email:${name}`, callback),
        setAttribute: (name, value) => attributes.set(name, value)
    };
    const error = {hidden: true, textContent: ''};
    const button = {
        addEventListener: (name, callback) => handlers.set(`login:${name}`, callback),
        focus: () => { focused = true; }
    };
    const contact = {
        classList: {
            toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name)
        }
    };
    const elements = new Map([
        ['[data-name="contact"]', contact],
        ['[data-name="contact-email"]', email],
        ['[data-name="contact-email-error"]', error]
    ]);
    const element = {
        querySelector: selector => {
            assert.ok(elements.has(selector), `Unknown data-name selector: ${selector}`);
            return elements.get(selector);
        },
        querySelectorAll: selector => {
            assert.equal(selector, '[data-name="login"]');
            return [button];
        }
    };
    let definition;
    vm.runInNewContext(
        readFileSync(`${__dirname}/../../bin/frontend/controls/SimpleCheckout.js`, 'utf8'),
        {
            Class: function (value) { return value; },
            define: (name, dependencies, factory) => { definition = factory({}, {}, {}, {}, {}, {}, {}); },
            require: (dependencies, resolve) => resolve(class {
                constructor(options) { loginOptions = options; }
                open() { opened = true; }
            })
        }
    );
    const instance = {
        ...definition,
        getId: () => 42,
        getElm: () => element,
        update: () => { updates++; }
    };
    instance.$bindContactEvents();

    return {
        instance, handlers, attributes, classes, email, error,
        updates: () => updates,
        focused: () => focused,
        loginOptions: () => loginOptions,
        opened: () => opened
    };
}

test('email changes update the checkout through its data-name hook', () => {
    const state = contact();
    state.handlers.get('email:change')();
    assert.equal(state.updates(), 1);
    assert.equal(state.attributes.get('aria-describedby'), state.error.id);
});

test('login opens a window without submitting the form and restores focus when closed', () => {
    const state = contact();
    let prevented = false;
    let stopped = false;
    state.handlers.get('login:click')({
        preventDefault: () => { prevented = true; },
        stopPropagation: () => { stopped = true; }
    });
    assert.equal(prevented, true);
    assert.equal(stopped, true);
    assert.equal(state.opened(), true);
    assert.equal(state.loginOptions().reload, false);
    assert.equal(state.loginOptions().redirect, false);
    state.loginOptions().events.onClose();
    assert.equal(state.focused(), true);
});

test('email errors are announced and cleared along with the visual state', () => {
    const state = contact();
    state.instance.$setContactEmailValidity(true);
    assert.equal(state.attributes.get('aria-invalid'), 'true');
    assert.equal(state.error.hidden, false);
    assert.equal(state.error.textContent, state.email.validationMessage);
    assert.equal(state.classes.has('quiqqer-simple-checkout-require'), true);

    state.instance.$setContactEmailValidity(false);
    assert.equal(state.attributes.get('aria-invalid'), 'false');
    assert.equal(state.error.hidden, true);
    assert.equal(state.error.textContent, '');
    assert.equal(state.classes.has('quiqqer-simple-checkout-require'), false);
});
