/**
 * Optional checkout URLs: ?product=123 or ?products=<URL-encoded JSON array or Base64url JSON>.
 * Products accept id/productId, quantity and fields. Add &addToBasket=1 to keep the current basket.
 */
define('package/quiqqer/order-simple-checkout/bin/frontend/utils/ProductUrl', [], function () {
    'use strict';

    const decode = (value) => {
        try {
            return JSON.parse(value);
        } catch (error) {
            const encoded = value.replace(/-/g, '+').replace(/_/g, '/');
            const binary = atob(encoded);
            const bytes = Uint8Array.from(binary, character => character.charCodeAt(0));
            return JSON.parse(new TextDecoder('utf-8', {fatal: true}).decode(bytes));
        }
    };

    const normalize = (product) => {
        if (typeof product === 'number' || typeof product === 'string') {
            product = {id: product};
        }

        if (!product || typeof product !== 'object' || Array.isArray(product)) {
            throw new Error('Invalid checkout product');
        }

        const rawId = product.id ?? product.productId;
        const rawQuantity = product.quantity ?? 1;
        const id = Number(rawId);
        const quantity = Number(rawQuantity);
        const fields = product.fields ?? {};

        if (
            !['number', 'string'].includes(typeof rawId) ||
            !['number', 'string'].includes(typeof rawQuantity) ||
            !Number.isSafeInteger(id) ||
            id <= 0 ||
            !Number.isFinite(quantity) ||
            quantity <= 0 ||
            !fields ||
            typeof fields !== 'object'
        ) {
            throw new Error('Invalid checkout product');
        }

        return {id, quantity, fields};
    };

    return {
        hasProducts: function (search) {
            const params = new URLSearchParams(search);
            return params.has('product') || params.has('products');
        },

        parse: function (search) {
            const params = new URLSearchParams(search);
            let products = null;

            if (params.has('products')) {
                products = decode(params.get('products'));

                if (!Array.isArray(products) || products.length === 0) {
                    throw new Error('Invalid checkout product list');
                }
            } else if (params.has('product')) {
                products = [{
                    id: params.get('product'),
                    quantity: params.get('quantity') ?? 1,
                    fields: params.has('fields') ? decode(params.get('fields')) : {}
                }];
            }

            return {
                products: products === null ? null : products.map(normalize),
                addToBasket: params.get('addToBasket') === '1' || params.get('addToBasket') === 'true'
            };
        },

        consume: function (href) {
            const url = new URL(href);

            for (const name of ['product', 'products', 'quantity', 'fields', 'addToBasket']) {
                url.searchParams.delete(name);
            }

            return url.href;
        }
    };
});
