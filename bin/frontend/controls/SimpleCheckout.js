define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout', [], function() {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: '',

        initialize: function(options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function() {

        }
    });
});
