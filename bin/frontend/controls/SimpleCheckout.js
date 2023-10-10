define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout', [

    'qui/QUI',
    'qui/controls/Control'

], function(QUI, QUIControl) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout',

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
