define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutDelivery', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/utils/Form',
    'Ajax'

], function(QUI, QUIControl, QUIFormUtils, QUIAjax) {
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

            if (Company) {
                this.$labels.push(Company);
            }

            if (VatId) {
                this.$labels.push(VatId);
            }

            if (chUID) {
                this.$labels.push(chUID);
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
            }

            BusinessType.fireEvent('change');
            this.getElm().getElements('input').addEvent('change', this.$onChange);
            loaded = true;
        },

        $onChange: function() {
            this.fireEvent('change');
        },

        $hideB2B: function() {
            this.$labels.forEach((Label) => {
                Label.setStyle('position', 'relative');
                Label.setStyle('overflow', 'hidden');
            });

            moofx(this.$labels).animate({
                height: 0,
                opacity: 0,
                margin: 0,
                padding: 0
            }, {
                callback: () => {
                    this.$labels.forEach((Label) => {
                        Label.setStyle('display', 'none');
                    });
                }
            });
        },

        $showB2B: function() {
            this.$labels.forEach((Label) => {
                Label.setStyle('position', 'relative');
                Label.setStyle('opacity', 0);
                Label.setStyle('display', null);
                Label.setStyle('overflow', 'hidden');
                Label.setStyle('height', 0);
                Label.setStyle('margin', null);

                moofx(Label).animate({
                    opacity: 1,
                    height: Label.getScrollSize().y
                });
            });
        }
    });
});
