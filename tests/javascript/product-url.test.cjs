const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const vm = require('node:vm');

let ProductUrl;
vm.runInNewContext(
    readFileSync(`${__dirname}/../../bin/frontend/utils/ProductUrl.js`, 'utf8'),
    {
        URL,
        URLSearchParams,
        TextDecoder,
        atob,
        define: (name, dependencies, factory) => { ProductUrl = factory(); }
    }
);
const plain = value => JSON.parse(JSON.stringify(value));
const query = values => `?${new URLSearchParams(values)}`;

test('the legacy product URL still selects one item with quantity one', () => {
    assert.deepEqual(plain(ProductUrl.parse('?product=123')), {
        products: [{id: 123, quantity: 1, fields: {}}],
        addToBasket: false
    });
});

test('URL encoded JSON supports multiple products, quantities, fields and productId aliases', () => {
    const products = [
        {id: 123, quantity: 2, fields: {'101': 'Größe L & Farbe weiß'}},
        {productId: 456, quantity: 1.5, fields: [{id: 102, value: 'Äpfel + Birnen'}]}
    ];
    const parsed = ProductUrl.parse(query({products: JSON.stringify(products)}));
    assert.deepEqual(plain(parsed.products), [
        products[0],
        {id: 456, quantity: 1.5, fields: products[1].fields}
    ]);
    assert.equal(parsed.addToBasket, false);
});

for (const encoding of ['base64', 'base64url']) {
    test(`${encoding} JSON preserves Unicode field values`, () => {
        const products = [{id: 123, fields: {'101': 'Größe: groß 😃'}}];
        const encoded = Buffer.from(JSON.stringify(products)).toString(encoding);
        const parsed = ProductUrl.parse(query({products: encoded}));
        assert.deepEqual(plain(parsed.products), [{...products[0], quantity: 1}]);
    });
}

test('legacy product URLs optionally accept quantity and encoded fields', () => {
    const fields = {'101': 'Äpfel'};
    for (const encoded of [JSON.stringify(fields), Buffer.from(JSON.stringify(fields)).toString('base64url')]) {
        const parsed = ProductUrl.parse(query({product: '123', quantity: '3', fields: encoded}));
        assert.deepEqual(plain(parsed.products), [{id: 123, quantity: 3, fields}]);
    }
});

test('a list of product IDs is accepted as shorthand', () => {
    assert.deepEqual(plain(ProductUrl.parse(query({products: '[123,"456"]'})).products), [
        {id: 123, quantity: 1, fields: {}},
        {id: 456, quantity: 1, fields: {}}
    ]);
});

test('structured products take precedence over the legacy product parameter', () => {
    assert.equal(ProductUrl.parse(query({product: 123, products: '[456]'})).products[0].id, 456);
});

test('append mode is optional and explicit', () => {
    for (const value of ['1', 'true']) {
        assert.equal(ProductUrl.parse(query({product: 123, addToBasket: value})).addToBasket, true);
    }
    for (const value of ['0', 'false', '', 'anything']) {
        assert.equal(ProductUrl.parse(query({product: 123, addToBasket: value})).addToBasket, false);
    }
});

test('a normal checkout URL does not supply products', () => {
    assert.equal(ProductUrl.hasProducts('?lang=de'), false);
    assert.equal(ProductUrl.parse('?lang=de').products, null);
    assert.equal(ProductUrl.hasProducts('?products='), true);
});

for (const search of [
    '?product=', '?product=abc', '?product=-1', '?product=1.5',
    '?product=123&quantity=0', '?product=123&quantity=-1', '?product=123&quantity=NaN',
    '?product=123&fields=broken', '?product=123&fields=1',
    ...['', '{}', 'null', '[]', 'not-json', '[null]', '[true]', '[{"id":true}]',
        '[{"id":1,"quantity":true}]', '[{"id":1,"fields":"bad"}]']
        .map(products => query({products}))
]) {
    test(`invalid selections are rejected: ${search}`, () => {
        assert.throws(() => ProductUrl.parse(search));
    });
}

test('consuming a selection keeps unrelated URL parameters and the checkout hash', () => {
    const consumed = new URL(ProductUrl.consume(
        'https://shop.example/checkout?lang=de&product=123&quantity=2&fields=x&products=y&addToBasket=1#order-hash'
    ));
    assert.equal(consumed.search, '?lang=de');
    assert.equal(consumed.hash, '#order-hash');
});

function checkout(search = '', options = {}, basket = null, post = null) {
    const requests = [];
    const history = [];
    let definition;
    const location = new URL(`https://shop.example/checkout${search}`);
    const ajax = {
        post: (name, callback, params) => {
            requests.push(params);
            if (post) { post(callback, params); } else { callback('new-order-hash'); }
        }
    };
    const context = {
        URL,
        URLSearchParams,
        window: {
            location,
            history: {
                state: {kept: true},
                replaceState: (state, title, href) => {
                    history.push({state, href});
                    location.href = href;
                }
            }
        },
        Class: function (value) { return value; },
        define: (name, dependencies, factory) => {
            definition = factory({addEvent() {}}, {}, {}, {}, {}, ajax, ProductUrl);
        },
        require: (dependencies, resolve) => resolve(basket)
    };
    vm.runInNewContext(
        readFileSync(`${__dirname}/../../bin/frontend/controls/SimpleCheckout.js`, 'utf8'),
        context
    );
    const attributes = {...definition.options, ...options};
    const instance = {
        ...definition,
        parent() {},
        addEvents() {},
        getAttribute: name => attributes[name],
        setAttribute: (name, value) => { attributes[name] = value; },
        $setAnchor: () => { location.hash = attributes.orderHash; }
    };
    instance.initialize(options);
    return {instance, attributes, requests, history, location};
}

test('legacy URLs still call the existing order endpoint', async () => {
    const state = checkout('?product=123');
    await state.instance.$loadProducts();
    assert.deepEqual(JSON.parse(state.requests[0].products), [{id: 123, quantity: 1, fields: {}}]);
    assert.equal(state.attributes.orderHash, 'new-order-hash');
    assert.equal(state.location.search, '');
    assert.equal(state.location.hash, '#new-order-hash');
});

test('existing JavaScript product configurations are forwarded unchanged', async () => {
    const products = [{productId: 123, quantity: 2, fields: {'101': 'existing'}}];
    const state = checkout('', {products});
    await state.instance.$loadProducts();
    assert.deepEqual(JSON.parse(state.requests[0].products), products);
    assert.equal(state.history.length, 0);
});

test('repeated initialization shares one request and adds the URL selection once', async () => {
    const state = checkout('?products=[123,456]');
    const first = state.instance.$loadProducts();
    const second = state.instance.$loadProducts();
    assert.equal(first, second);
    await Promise.all([first, second]);
    await state.instance.$loadProducts();
    assert.equal(state.requests.length, 1);
});

test('a checkout hash resumes the current order without applying URL products again', async () => {
    const state = checkout('?product=123#old-order', {orderHash: 'existing-order'});
    await state.instance.$loadProducts();
    assert.equal(state.requests.length, 0);
    assert.equal(state.history.length, 0);
    assert.equal(state.attributes.orderHash, 'existing-order');
});

test('invalid URL data never sends a request or changes the URL', async () => {
    const state = checkout('?products=broken');
    await assert.rejects(state.instance.$loadProducts());
    assert.equal(state.requests.length, 0);
    assert.equal(state.history.length, 0);
    assert.equal(state.attributes.orderHash, false);
});

test('request failures keep URL parameters and allow retry', async () => {
    let attempts = 0;
    const state = checkout('?product=123', {}, null, (callback, params) => {
        if (++attempts === 1) { params.onError(new Error('Network error')); } else { callback('retry-order'); }
    });
    await assert.rejects(state.instance.$loadProducts());
    assert.equal(state.location.search, '?product=123');
    await state.instance.$loadProducts();
    assert.equal(state.attributes.orderHash, 'retry-order');
});

function existingBasket(orderHash = 'basket-order') {
    const products = [{id: 99, quantity: 1, fields: {}}];
    const calls = [];
    return {
        products,
        calls,
        ready: async () => calls.push('ready'),
        existsProduct: async id => { calls.push(`check:${id}`); return true; },
        addProduct: async (id, quantity, fields) => {
            calls.push(`add:${id}`);
            products.push({id, quantity, fields});
        },
        save: async force => { assert.equal(force, true); calls.push('save'); },
        toOrder: async () => { calls.push('toOrder'); return orderHash; },
        getProducts: () => products.map(product => ({getAttributes: () => product}))
    };
}

test('append mode keeps existing products and uses the normal basket conversion', async () => {
    const Basket = existingBasket();
    const selection = [{id: 123, quantity: 2, fields: {'101': 'new'}}];
    const state = checkout(query({products: JSON.stringify(selection), addToBasket: 1}), {}, Basket);
    await state.instance.$loadProducts();
    assert.deepEqual(plain(Basket.products), [{id: 99, quantity: 1, fields: {}}, ...selection]);
    assert.deepEqual(Basket.calls, ['ready', 'check:123', 'add:123', 'save', 'toOrder']);
    assert.equal(state.requests.length, 0);
    assert.equal(state.attributes.orderHash, 'basket-order');
});

test('append mode can also be selected through JavaScript attributes', async () => {
    const Basket = existingBasket();
    const state = checkout('', {products: [{productId: 123}], addToBasket: true}, Basket);
    await state.instance.$loadProducts();
    assert.equal(Basket.products[0].id, 99);
    assert.equal(Basket.products[1].id, 123);
});

test('guest append mode forwards the complete local basket into guest checkout', async () => {
    const Basket = existingBasket(false);
    const state = checkout('?product=123&addToBasket=1', {}, Basket);
    await state.instance.$loadProducts();
    assert.deepEqual(JSON.parse(state.requests[0].products), plain(Basket.products));
    assert.deepEqual(Basket.products.map(product => product.id), [99, 123]);
});

test('unavailable append products do not modify the existing basket', async () => {
    const Basket = existingBasket();
    Basket.existsProduct = async () => false;
    const state = checkout('?product=123&addToBasket=1', {}, Basket);
    await assert.rejects(state.instance.$loadProducts());
    assert.deepEqual(Basket.products.map(product => product.id), [99]);
    assert.equal(state.requests.length, 0);
    assert.equal(state.history.length, 0);
});
