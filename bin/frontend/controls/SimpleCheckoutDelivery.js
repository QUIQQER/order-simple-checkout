define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutDelivery', [

    'qui/QUI',
    'qui/controls/Control'

], function(QUI, QUIControl) {
    'use strict';

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

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function() {
            let loaded = false;
            const BusinessType = this.getElm().getElement('[name="businessType"]');
            const Company = this.getElm().getElement('.quiqqer-order-customerData-edit-company');
            const VatId = this.getElm().getElement('.quiqqer-order-customerData-edit-vatId');
            const chUID = this.getElm().getElement('.quiqqer-order-customerData-edit-chUID');

            VatId.setStyle('display', null);
            chUID.setStyle('display', 'none');

            // country change
            const CountryNode = this.getElm().getElement('[name="country"]');

            if (CountryNode) {
                new Promise((resolve) => {
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

                    this.$Countries.addEvent('change', () => {
                        if (this.$Countries.getValue() === 'CH') {
                            chUID.setStyle('display', null);
                            VatId.setStyle('display', 'none');
                        } else {
                            chUID.setStyle('display', 'none');
                            VatId.setStyle('display', null);
                        }
                    });
                });
            }

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

                    if (loaded) {
                        this.$onChange();
                    }
                });

                BusinessType.fireEvent('change');
            }

            this.getElm().getElements('input').addEvent('change', this.$onChange);
            loaded = true;
        },

        $onChange: function() {
            this.fireEvent('change');
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
