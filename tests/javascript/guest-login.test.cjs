const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const vm = require('node:vm');

function checkout() {
    let definition;
    let options;
    let reloads = 0;
    let updates = 0;
    let focused = false;
    const email = {value: 'known@example.test', focus() { focused = true; }};
    const requests = [];
    const duplicate = {
        getCode: () => 401,
        getAttribute: name => name === 'authenticationRequired' ? true : null,
        getMessage: () => 'Diese E-Mail-Adresse ist bereits registriert. Bitte melde dich an.'
    };
    vm.runInNewContext(
        readFileSync(`${__dirname}/../../bin/frontend/controls/SimpleCheckout.js`, 'utf8'),
        {
            Class: function (value) { return value; },
            define: (name, dependencies, factory) => {
                definition = factory({}, {}, {}, {getFormData: () => ({email: email.value})}, {}, {
                    post(name, callback, params) {
                        requests.push({name, params});
                        params.onError(duplicate);
                    }
                }, {});
            },
            window: {location: {reload() { reloads++; }}},
            JSON: {encode: JSON.stringify},
            require: (dependencies, resolve) => {
                assert.equal(dependencies[0], 'controls/users/LoginWindow');
                resolve(class {
                    constructor(value) { options = value; }
                    open() {}
                });
            }
        }
    );
    const instance = {
        ...definition,
        $isOrdering: false,
        $initialized: true,
        $BasketLoader: {style: {}},
        Loader: {show() {}, hide() {}},
        validate: () => true,
        update: async () => { updates++; },
        fireEvent() {},
        $setPayButtonDisabled() {},
        $setContactEmailValidity() {},
        getAttribute: () => 'test-order',
        getElm: () => ({querySelector: () => email, getElement: () => ({elements: {}})})
    };

    return {instance, requests, duplicate, email, update: definition.update.bind(instance), options: () => options, reloads: () => reloads, updates: () => updates, focused: () => focused};
}

test('a registered guest email opens the login with an explanation and stops the order', async () => {
    const state = checkout();
    const result = await state.instance.orderWithCosts();

    assert.equal(result, false);
    assert.equal(state.requests.length, 1);
    assert.equal(state.requests[0].params.showLogin, false);
    assert.equal(state.requests[0].params.showError, false);
    assert.equal(state.options().message, state.duplicate.getMessage());
    assert.equal(state.instance.$isOrdering, false);
    assert.equal(state.instance.$orderPromise, null);
});

test('closing the login clears only the email, saves it and leaves the checkout open', () => {
    const state = checkout();
    state.instance.$openGuestAccountLogin(state.duplicate.getMessage());
    state.options().events.onClose();

    assert.equal(state.email.value, '');
    assert.equal(state.focused(), true);
    assert.equal(state.updates(), 1);
    assert.equal(state.reloads(), 0);
    assert.equal(state.requests.length, 0);
});

test('successful login reloads the page without clearing the email or retrying the order', () => {
    const state = checkout();
    state.instance.$openGuestAccountLogin(state.duplicate.getMessage());
    state.options().events.onSuccess();
    state.options().events.onClose();

    assert.equal(state.reloads(), 1);
    assert.equal(state.email.value, 'known@example.test');
    assert.equal(state.updates(), 0);
    assert.equal(state.requests.length, 0);
});

test('an email change opens the same explanatory login when the account exists', async () => {
    const state = checkout();
    const result = await state.update();

    assert.equal(result, false);
    assert.match(state.requests[0].name, /frontend_update$/);
    assert.equal(state.requests[0].params.showLogin, false);
    assert.equal(state.options().message, state.duplicate.getMessage());
});

test('an update rejected for an existing account never continues to place the order', async () => {
    const state = checkout();
    state.instance.update = state.update;
    const result = await state.instance.orderWithCosts();

    assert.equal(result, false);
    assert.equal(state.requests.length, 1);
    assert.match(state.requests[0].name, /frontend_update$/);
    assert.equal(state.instance.$isOrdering, false);
});
