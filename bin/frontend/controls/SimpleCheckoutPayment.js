define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutPayment', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax'

], function(QUI, QUIControl, QUIAjax) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutPayment',

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
            const Elm = this.getElm();
            const PaymentOptionElms = Elm.getElements('[name="payment"]');

            PaymentOptionElms.addEvent('change', this.$onChange);
            PaymentOptionElms.forEach((Node) => {
                Node.required = true;

                Node.getParent('.quiqqer-order-step-payments-list-entry').addEvent('click', (e) => {
                    let Target = e.target;

                    if (!Target.hasClass('quiqqer-order-step-payments-list-entry')) {
                        Target = Target.getParent('.quiqqer-order-step-payments-list-entry');
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

            return new Promise((resolve) => {
                if (!this.getAttribute('Checkout')) {
                    return new Promise((r) => {
                        (() => {
                            return this.refresh().then(r);
                        }).delay(200);
                    });
                }

                QUIAjax.get('package_quiqqer_order-simple-checkout_ajax_frontend_payments', (html) => {
                    const Ghost = new Element('div', {
                        html: html
                    });

                    this.getElm().set('html', Ghost.getFirst('div').get('html'));
                    Ghost.getElements('style').inject(this.getElm());

                    QUI.parse(this.getElm()).then(() => {
                        this.$registerEvents();

                        if (this.getElm().querySelectorAll('input').length === 1) {
                            this.getElm().querySelector('input').click();
                        }

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
