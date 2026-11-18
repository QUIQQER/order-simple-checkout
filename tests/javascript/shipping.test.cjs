const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const vm = require('node:vm');

function shipping() {
    let definition;
    vm.runInNewContext(
        readFileSync(`${__dirname}/../../bin/frontend/controls/SimpleCheckoutShipping.js`, 'utf8'),
        {
            Class: function (value) { return value; },
            define: (name, dependencies, factory) => { definition = factory({}, {}, {}); }
        }
    );

    return definition;
}

test('shipping is hidden for digital products and restored for physical products after refresh', () => {
    const section = {hidden: false};
    let digitalOnly = true;
    const instance = {
        ...shipping(),
        getElm: () => ({
            closest: selector => {
                assert.equal(selector, '[data-name="shipping-section"]');
                return section;
            },
            querySelector: selector => {
                assert.equal(selector, '[data-name="shipping-not-required"]');
                return digitalOnly ? {} : null;
            }
        })
    };

    instance.$syncVisibility();
    assert.equal(section.hidden, true);

    digitalOnly = false;
    instance.$syncVisibility();
    assert.equal(section.hidden, false);

    digitalOnly = true;
    instance.$syncVisibility();
    assert.equal(section.hidden, true);
});

test('custom templates can omit the shipping section wrapper', () => {
    const instance = {
        ...shipping(),
        getElm: () => ({closest: () => null})
    };

    assert.doesNotThrow(() => instance.$syncVisibility());
});
