define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutPayment', [

    'qui/QUI',
    'qui/controls/Control'

], function(QUI, QUIControl) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutPayment',

        initialize: function(options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function() {

        },

        refresh: function() {
            
        }
    });
});
