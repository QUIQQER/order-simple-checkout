define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/utils/Form',
    'Ajax'

], function(QUI, QUIControl, QUIFormUtils, QUIAjax) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout',

        Binds: [
            'update'
        ],

        initialize: function(options) {
            this.parent(options);

            this.$Delivery = null;
            this.$Shipping = null;
            this.$Payment = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function() {
            Promise.all([
                this.$getControl(this.getElm().getElement('.quiqqer-simple-checkout-delivery')),
                this.$getControl(this.getElm().getElement('.quiqqer-simple-checkout-shipping')),
                this.$getControl(this.getElm().getElement('.quiqqer-simple-checkout-payment'))
            ]).then((instances) => {
                console.log(instances);

                this.$Delivery = instances[0];
                this.$Shipping = instances[1];
                this.$Payment = instances[2];

                this.$Delivery.addEvent('change', () => {
                    this.update().then(() => {
                        if (this.$Shipping) {
                            return this.$Shipping.refresh().then(() => {
                                this.$Payment.refresh();
                            });
                        }

                        this.$Payment.refresh();
                    });
                });

                if (this.$Shipping) {
                    this.$Shipping.addEvent('change', () => {
                        this.update().then(() => {
                            this.$Payment.refresh();
                        });
                    });
                }

                this.$Payment.addEvent('change', () => {
                    this.update().then(() => {

                    });
                });
            });
        },

        $getControl: function(Node) {
            return new Promise((resolve) => {
                if (!Node.get('data-qui')) {
                    return resolve(null);
                }

                if (Node.get('data-quiid')) {
                    resolve(QUI.Controls.getById(Node.get('data-quiid')));
                    return;
                }

                Node.addEvent('load', () => {
                    resolve(QUI.Controls.getById(Node.get('data-quiid')));
                });
            });
        },

        update: function() {
            console.log('update');

            return new Promise((resolve) => {
                const orderData = QUIFormUtils.getFormData(this.getElm().getElement('form'));

                QUIAjax.post('package_quiqqer_order-simple-checkout_ajax_frontend_update', resolve, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderData: JSON.encode(orderData)
                });
            });
        }
    });
});
