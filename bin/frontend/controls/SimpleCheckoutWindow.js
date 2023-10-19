define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutWindow', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout'

], function(QUI, QUIWindow, SimpleCheckout) {
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

            this.$Checkout = null;

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        $onOpen: function() {
            if (this.$Checkout) {
                return;
            }

            this.Loader.show();
            this.getContent().set('html', '');
            this.getContent().setStyle('padding', 0);

            this.$Checkout = new SimpleCheckout({
                products: this.getAttribute('products'),
                events: {
                    onLoaded: () => {
                        this.Loader.hide();
                    },
                    onLoadedError: () => {
                        require([
                            'package/quiqqer/frontend-users/bin/frontend/controls/login/Window'
                        ], (LoginWindow) => {
                            this.close();

                            new LoginWindow({
                                events: {
                                    onSuccess: () => {
                                        this.$Checkout.destroy();
                                        this.$Checkout = null;
                                        this.open();
                                    }
                                }
                            }).open();
                        });

                    }
                }
            }).inject(this.getContent());
        }
    });
});
