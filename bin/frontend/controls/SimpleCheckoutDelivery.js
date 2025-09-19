define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutDelivery', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax'

], function(QUI, QUIControl, QUIAjax) {
    'use strict';

    let loading = false;

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutDelivery',

        Binds: [
            '$onChange'
        ],

        initialize: function(options) {
            this.parent(options);

            this.$labels = [];
            this.$Countries = null;
            this.$changeTimeout = null;
            this.$Loader = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function() {
            this.$labels = [];

            this.$Loader = new Element('span', {
                'class': 'fa fa-spin fa-circle-notch simpleCheckout-details-section-loader'
            }).inject(this.getElm().getParent('.simpleCheckout-details-section'));

            this.$registerEvents().then(() => {
                loading = true;
                this.$Loader.style.display = 'none';
            });
        },

        refresh: function() {
            const Addresses = this.getElm().getElement('[name="addresses"]');

            if (!Addresses) {
                return Promise.resolve();
            }

            loading = true;
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

                QUIAjax.get('package_quiqqer_order-simple-checkout_ajax_frontend_delivery', (html) => {
                    const Ghost = new Element('div', {
                        html: html
                    });

                    this.getElm().set('html', Ghost.getFirst('div').get('html'));
                    Ghost.getElements('style').inject(this.getElm());

                    QUI.parse(this.getElm()).then(() => {
                        return this.$registerEvents();
                    }).then(() => {
                        this.fireEvent('refreshEnd', [this]);
                        this.$Loader.style.display = 'none';

                        loading = false;
                        this.$onChange();

                        resolve();
                    });
                }, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderHash: this.getAttribute('Checkout').getAttribute('orderHash'),
                    addressId: Addresses.value
                });
            });
        },

        $registerEvents: function() {
            const BusinessType = this.getElm().getElement('[name="businessType"]');
            const Company = this.getElm().getElement('.quiqqer-order-customerData-edit-company');
            const VatId = this.getElm().getElement('.quiqqer-order-customerData-edit-vatId');
            const chUID = this.getElm().getElement('.quiqqer-order-customerData-edit-chUID');
            const Addresses = this.getElm().getElement('[name="addresses"]');

            if (Addresses) {
                // disable all addresses
                this.getElm().querySelectorAll('select,input').forEach((Node) => {
                    if (Node.name !== 'addresses') {
                        //Node.disabled = true;
                    }
                });

                Addresses.addEventListener('change', () => {
                    this.refresh();
                });
            }

            VatId ? VatId.setStyle('display', null) : '';
            chUID ? chUID.setStyle('display', 'none') : '';

            if (Company) {
                this.$labels.push(Company);
            }

            if (BusinessType) {
                BusinessType.addEvent('change', () => {
                    if (BusinessType.value === 'b2c') {
                        this.$hideB2B();
                    }

                    if (BusinessType.value === 'b2b') {
                        this.$showB2B();
                    }

                    if (loading) {
                        this.$onChange();
                    }
                });

                BusinessType.fireEvent('change');
            }

            this.getElm().getElements('input').addEvent('change', this.$onChange);

            // country change
            const CountryNode = this.getElm().getElement('[name="country"]');

            if (!CountryNode) {
                return Promise.resolve();
            }

            return new Promise((resolve) => {
                if (CountryNode.get('data-quiid')) {
                    resolve(QUI.Controls.getById(CountryNode.get('data-quiid')));
                    return;
                }

                let checkInterval = setInterval(() => {
                    if (CountryNode.get('data-quiid')) {
                        clearInterval(checkInterval);
                        resolve(QUI.Controls.getById(CountryNode.get('data-quiid')));
                    }
                }, 10);
            }).then((QUICountries) => {
                this.$Countries = QUICountries;

                if (Addresses) {
                    this.$Countries.disable();
                }

                this.$Countries.addEvent('change', () => {
                    if (this.$Countries.getValue() === 'CH') {
                        chUID.setStyle('display', null);
                        VatId.setStyle('display', 'none');
                    } else {
                        chUID.setStyle('display', 'none');
                        VatId.setStyle('display', null);
                    }

                    this.$onChange();
                });
            });
        },

        $onChange: function() {
            if (!loading) {
                return;
            }

            if (this.$changeTimeout) {
                clearTimeout(this.$changeTimeout);
            }

            this.$changeTimeout = setTimeout(() => {
                this.fireEvent('change');
            }, 50);
        },

        $hideB2B: function() {
            const VatId = this.getElm().getElement('.quiqqer-order-customerData-edit-vatId');
            const chUID = this.getElm().getElement('.quiqqer-order-customerData-edit-chUID');

            const labels = this.$labels.concat([VatId, chUID]);

            labels.forEach((Label) => {
                Label.setStyle('position', 'relative');
                Label.setStyle('overflow', 'hidden');
            });

            moofx(labels).animate({
                height: 0,
                opacity: 0,
                margin: 0,
                padding: 0
            }, {
                callback: () => {
                    labels.forEach((Label) => {
                        Label.setStyle('display', 'none');
                    });
                }
            });
        },

        $showB2B: function() {
            const VatId = this.getElm().getElement('.quiqqer-order-customerData-edit-vatId');
            const chUID = this.getElm().getElement('.quiqqer-order-customerData-edit-chUID');
            const labels = this.$labels.concat([VatId, chUID]);

            labels.forEach((Label) => {
                Label.setStyle('position', 'relative');
                Label.setStyle('opacity', 0);
                Label.setStyle('display', null);
                Label.setStyle('overflow', 'hidden');
                Label.setStyle('height', 0);
                Label.setStyle('margin', null);

                moofx(Label).animate({
                    opacity: 1,
                    height: Label.getScrollSize().y
                }, {
                    callback: () => {
                        if (this.$Countries) {
                            this.$Countries.fireEvent('change');
                        }
                    }
                });
            });
        }
    });
});
