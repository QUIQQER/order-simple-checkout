const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const vm = require('node:vm');

function checkout(basket, options = {}) {
    let empty = true;
    let hasButton = true;
    let definition;
    let requests = 0;
    const handlers = new Map();
    const button = {
        disabled: false,
        addEventListener: (name, callback) => handlers.set(name, callback),
        remove: () => { hasButton = false; }
    };
    const sections = new Map([
        ['empty-checkout', {hidden: true}],
        ['checkout-form', {hidden: false}],
        ['order-summary', {hidden: false}],
        ['scroll-to-payment', {hidden: false}]
    ]);
    const element = {
        querySelector: selector => {
            const name = selector.match(/^\[data-name="([^"]+)"\]$/)?.[1];
            assert.ok(name, `Expected a data-name selector, got ${selector}`);
            if (name === 'basket-empty') return empty ? {} : null;
            if (name === 'pay') return hasButton ? button : null;
            return sections.get(name) ?? null;
        }
    };
    vm.runInNewContext(
        readFileSync(`${__dirname}/../../bin/frontend/controls/SimpleCheckout.js`, 'utf8'),
        {
            Class: function (value) { return value; },
            define: (name, dependencies, factory) => {
                definition = factory({}, {}, {}, {}, {}, {post() { requests++; }}, {});
            },
            require: (dependencies, resolve) => resolve(basket)
        }
    );
    const attributes = {...definition.options, ...options};
    const instance = {
        ...definition,
        $isOrdering: false,
        getAttribute: name => attributes[name],
        setAttribute: (name, value) => { attributes[name] = value; },
        getElm: () => element
    };
    return {
        instance, button, sections,
        requests: () => requests,
        setEmpty: value => { empty = value; },
        removeButton: () => { hasButton = false; }
    };
}

test('an empty basket finishes loading without creating an order or retrying', async () => {
    let conversions = 0;
    const state = checkout({
        isLoaded: () => true,
        count: () => 0,
        toOrder: async () => { conversions++; return false; }
    });

    const result = await state.instance.$loadOrder();
    assert.equal(result.order, false);
    assert.equal(conversions, 0);
    assert.equal(state.requests(), 0);
});

test('a failed basket conversion does not recurse into another order request', async () => {
    let conversions = 0;
    const state = checkout({
        isLoaded: () => true,
        count: () => 1,
        toOrder: async () => { conversions++; return false; }
    });

    const result = await state.instance.$loadOrder();
    assert.equal(result.order, false);
    assert.equal(conversions, 1);
    assert.equal(state.requests(), 0);
});

test('an empty basket shows only the empty view and disables ordering', () => {
    const state = checkout();
    state.instance.$bindPayButton();
    state.instance.$syncBasketState();

    assert.equal(state.button.disabled, true);
    assert.equal(state.sections.get('empty-checkout').hidden, false);
    assert.equal(state.sections.get('checkout-form').hidden, true);
    assert.equal(state.sections.get('order-summary').hidden, true);
    assert.equal(state.sections.get('scroll-to-payment').hidden, true);
});

test('adding products restores the checkout and re-enables ordering', () => {
    const state = checkout();
    state.instance.$bindPayButton();
    state.instance.$syncBasketState();
    state.setEmpty(false);
    state.instance.$syncBasketState();

    assert.equal(state.sections.get('empty-checkout').hidden, true);
    assert.equal(state.sections.get('checkout-form').hidden, false);
    assert.equal(state.button.disabled, false);
    state.instance.$setPayButtonDisabled(true);
    assert.equal(state.button.disabled, true);
});

test('custom templates can omit the order button without a null access', () => {
    const state = checkout();
    state.removeButton();

    assert.doesNotThrow(() => {
        state.instance.$bindPayButton();
        state.instance.$syncBasketState();
    });
    assert.equal(state.instance.$PayToOrderBtn, null);
});
