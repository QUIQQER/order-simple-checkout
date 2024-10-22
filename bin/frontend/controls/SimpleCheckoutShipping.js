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

            this.$Loader = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function() {
            this.$registerEvents();

            this.$Loader = new Element('span', {
                'class': 'fa fa-spin fa-circle-notch simpleCheckout-details-section-loader'
            }).inject(this.getElm().getParent('.simpleCheckout-details-section'));

            this.$Loader.style.display = 'none';
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
            this.$Loader.style.display = '';
            this.fireEvent('refreshBegin', [this]);

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
                        this.$Loader.style.display = 'none';
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
