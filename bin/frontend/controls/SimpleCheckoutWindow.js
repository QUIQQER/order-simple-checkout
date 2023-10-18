define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutWindow', [

    'qui/QUI',
    'qui/controls/windows/Popup'

], function(QUI, QUIWindow) {
    'use strict';

    return new Class({

        Extends: QUIWindow,
        Type: 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutWindow',

        Binds: [
            '$onOpen'
        ],

        initialize: function(options) {
            this.parent(options);

            this.setAttributes({
                buttons: false,
                maxHeight: 800,
                maxWidth: 1200
            });

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        $onOpen: function() {
            this.Loader.show();
            this.getContent().set('html', '');
            this.getContent().setStyle('padding', 0);

            require([
                'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout'
            ], (SimpleCheckout) => {

                const Checkout = new SimpleCheckout({
                    events: {
                        onLoaded: () => {
                            this.Loader.hide();
                        }
                    }
                }).inject(this.getContent());
            });
        }
    });
});
