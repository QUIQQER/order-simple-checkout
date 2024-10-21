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
            '$toggle',
            '$showAddressContainer',
            '$hideAddressContainer'
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
            this.$labels = this.getElm().getElements('.quiqqer-simple-checkout-billing__entry');

            this.$InputSame.addEvent('click', this.$toggle);
            this.$InputDiff.addEvent('click', this.$toggle);

            this.getElm().getElements('input').addEvent('change', this.$onChange);
        },

        $toggle: function(event) {
            this.$labels.removeClass('selected');

            const Input = event.target,
                Label = event.target.getParent('label');

            if (Input.checked) {
                Label.classList.add('selected');
            }

            if (this.$InputSame.checked) {
                this.$hideAddressContainer();
            } else {
                this.$showAddressContainer();
            }

            this.$onChange();
        },

        $onChange: function() {
            this.fireEvent('change');
        },

        $showAddressContainer: function() {
            this.$Container.setStyle('height', 0);
            this.$Container.setStyle('display', null);
            const Inner = this.$Container.getElement('.inner');

            return new Promise((resolve) => {
                moofx(this.$Container).animate({
                    height: Inner.offsetHeight
                }, {
                    callback: () => {
                        this.$Container.setStyle('height', null);
                        resolve();
                    }
                });
            });
        },

        $hideAddressContainer: function() {
            return new Promise((resolve) => {
                moofx(this.$Container).animate({
                    height: 0
                }, {
                    callback: () => {
                        this.$Container.setStyle('height', 0);
                        this.$Container.setStyle('display', null);
                        resolve();
                    }
                });
            });
        }
    });
});
