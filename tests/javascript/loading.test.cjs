const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const vm = require('node:vm');

function deferred() {
    let resolve;
    const promise = new Promise(complete => { resolve = complete; });
    return {promise, resolve};
}

function definition() {
    let result;
    vm.runInNewContext(
        readFileSync(`${__dirname}/../../bin/frontend/controls/SimpleCheckout.js`, 'utf8'),
        {
            Class: function (value) { return value; },
            define: (name, dependencies, factory) => {
                result = factory({}, {}, {}, {}, {}, {}, {hasProducts: () => true});
            },
            window: {location: {search: '?product=2260&quantity=1'}},
            moofx: () => ({animate() {}})
        }
    );
    return result;
}

test('the loader stays active until both URL products and the checkout GUI finish loading', async () => {
    const products = deferred();
    const gui = deferred();
    const guiStarted = deferred();
    const attributes = new Map();
    let hidden = 0;
    let synced = 0;
    const instance = {
        ...definition(),
        $initialLoadComplete: false,
        Loader: {hide() { hidden++; }},
        getAttribute: () => null,
        setAttribute() {},
        getElm: () => ({
            getElement: () => null,
            getElements: () => [],
            setAttribute: (name, value) => attributes.set(name, value)
        }),
        $loadProducts: () => products.promise,
        $loadOrder: async () => ({order: false}),
        $loadGUI: () => { guiStarted.resolve(); return gui.promise; },
        $parseTermsAndConditions() {},
        $syncBasketState() { synced++; }
    };

    const loading = instance.$startInitialLoad();
    assert.equal(instance.$initialLoadComplete, false);
    assert.equal(hidden, 0);
    assert.equal(synced, 0);

    products.resolve();
    await guiStarted.promise;
    assert.equal(instance.$initialLoadComplete, false);
    assert.equal(hidden, 0);
    assert.equal(synced, 0);

    gui.resolve();
    await loading;
    assert.equal(instance.$initialLoadComplete, true);
    assert.equal(hidden, 1);
    assert.equal(synced, 1);
    assert.equal(attributes.get('aria-busy'), 'false');
});

test('GUI initialization returns a promise that waits for the basket refresh', async () => {
    const basket = deferred();
    const refreshStarted = deferred();
    let complete = false;
    const instance = {
        ...definition(),
        getElm: () => ({removeClass() {}, getElement: () => null}),
        $getControl: async () => null,
        $refreshBasket: () => { refreshStarted.resolve(); return basket.promise; }
    };

    const loading = instance.$loadGUI();
    assert.equal(typeof loading.then, 'function');
    loading.then(() => { complete = true; });
    await refreshStarted.promise;
    assert.equal(complete, false);

    basket.resolve();
    await loading;
    assert.equal(complete, true);
});
