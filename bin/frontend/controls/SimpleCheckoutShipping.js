define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutShipping', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax'

], function(QUI, QUIControl, QUIAjax) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutShipping',

        Binds: [
            '$onChange'
        ],

        initialize: function(options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function() {
            this.$registerEvents();
        },

        $registerEvents: function() {
            this.getElm().getElements('[type="radio"]').addEvent('change', this.$onChange);
            this.getElm().getElements('[type="radio"]').forEach((Node) => {
                Node.getParent('.quiqqer-order-step-shipping-list-entry').addEvent('click', (e) => {
                    let Target = e.target;

                    if (!Target.hasClass('quiqqer-order-step-shipping-list-entry')) {
                        Target = Target.getParent('.quiqqer-order-step-shipping-list-entry');
                    }

                    Target.getElement('input').set('checked', true);
                    this.$onChange();
                });
            });
        },

        $onChange: function(e) {
            if (typeOf(e) === 'domevent') {
                e.stop();
            }

            this.fireEvent('change');
        },

        refresh: function() {
            this.fireEvent('refreshBegin', [this]);

            // @todo loader
            if (!this.getAttribute('Checkout')) {
                return new Promise((r) => {
                    (() => {
                        return this.refresh().then(r);
                    }).delay(200);
                });
            }

            return new Promise((resolve) => {
                QUIAjax.get('package_quiqqer_order-simple-checkout_ajax_frontend_shipping', (html) => {
                    const Ghost = new Element('div', {
                        html: html
                    });

                    this.getElm().set('html', Ghost.getFirst('div').get('html'));
                    Ghost.getElements('style').inject(this.getElm());

                    QUI.parse(this.getElm()).then(() => {
                        this.$registerEvents();
                        this.fireEvent('refreshEnd', [this]);
                        resolve();
                    });
                }, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderHash: this.getAttribute('Checkout').getAttribute('orderHash')
                });
            });
        }
    });
});
