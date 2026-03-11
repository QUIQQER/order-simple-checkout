/**
 * @event onCancel [this] - Fires if the users cancels the order process
 * @event onCloseOrderSuccessful [this] - Fires if the user closes the checkout window after a successful order
 * @event showOrderSuccessInfo [SimpleCheckoutControl, this] - Fires if last step of successful order is shown
 */
define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutWindow', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'Locale',
    'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout',
    'css!package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutWindow.css'

], function(QUI, QUIWindow, QUIButton, QUILocale, SimpleCheckout) {
    'use strict';

    return new Class({

        Extends: QUIWindow,
        Type: 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutWindow',

        Binds: [
            '$onOpen'
        ],

        options: {
            'class': 'SimpleCheckoutWindow',
            closeButton: true,
            showOrderSuccessInfo: true
        },

        initialize: function(options) {
            this.setAttributes({
                maxHeight: 10000, // workaround, qui popup
                maxWidth: 10000, // does not support full screen
                draggable: false,
                resizable: false,
                buttons: false,
                closeButton: false,
                title: false
            });

            this.parent(options);
            this.$Checkout = null;
            this.$PayToOrderBtn = null;

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

            new Element('button', {
                'class': 'SimpleCheckoutWindow__btnClose',
                html: '<i class="fa fa-times"></i>',
                events: {
                    click: () => {
                        this.close();
                    }
                }
            }).inject(this.getContent());

            const CheckoutWrapper = new Element('div', {
                'class': 'SimpleCheckoutWindow__checkoutWrapper'
            }).inject(this.getContent());

            this.$Checkout = new SimpleCheckout({
                products: this.getAttribute('products'),
                showPayToOrderBtn: true,
                showOrderSuccessInfo: this.getAttribute('showOrderSuccessInfo'),
                events: {
                    onLoaded: () => {
                        this.Loader.hide();
                    },
                    onOrderStart: () => {
                        this.Loader.show();
                    },
                    onOrderSuccessful: () => {
                        new Fx.Scroll(CheckoutWrapper).toTop();

                        if (!this.getAttribute('showOrderSuccessInfo')) {
                            this.Loader.hide();
                        }

                        this.fireEvent('orderSuccessful', [this]);
                    },
                    onShowOrderSuccessInfo: (SimpleCheckoutControl) => {
                        this.Loader.hide();
                        this.fireEvent('showOrderSuccessInfo', [SimpleCheckoutControl, this]);
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
            }).inject(CheckoutWrapper);
        }
    });
});
