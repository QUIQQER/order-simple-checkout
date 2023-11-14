define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutBillingAddress', [

    'qui/QUI',
    'qui/controls/Control'

], function(QUI, QUIControl) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutBillingAddress',

        Binds: [
            '$onChange',
            '$onImport',
            '$toggle'
        ],

        initialize: function(options) {
            this.parent(options);

            this.$Container = null;
            this.$InputSame = null;
            this.$InputDiff = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function() {
            this.$Container = this.getElm().getElement('.quiqqer-simple-checkout-billing-diffContainer');
            this.$InputSame = this.getElm().getElement('[value="same_as_shipping"]');
            this.$InputDiff = this.getElm().getElement('[value="different"]');

            this.$InputSame.addEvent('click', this.$toggle);
            this.$InputDiff.addEvent('click', this.$toggle);

            this.getElm().getElements('input').addEvent('change', this.$onChange);
        },

        $toggle: function() {
            if (this.$InputSame.checked) {
                this.$Container.setStyle('display', 'none');
            } else {
                this.$Container.setStyle('display', 'inline-block');
            }

            this.$onChange();
        },

        $onChange: function() {
            this.fireEvent('change');
        }
    });
});
