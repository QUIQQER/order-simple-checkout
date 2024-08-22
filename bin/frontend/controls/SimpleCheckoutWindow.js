/**
 * @event onCancel [this] - Fires if the users cancels the order process
 * @event onCloseOrderSuccessful [this] - Fires if the user closes the checkout window after a successful order
 */
define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutWindow', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'Locale',
    'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout',
    'css!package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutWindow.css',

], function (QUI, QUIWindow, QUIButton, QUILocale, SimpleCheckout) {
    'use strict';

    return new Class({

        Extends: QUIWindow,
        Type   : 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutWindow',

        Binds: [
            '$onOpen'
        ],

        options: {
            'class'    : 'SimpleCheckoutWindow',
            closeButton: false
        },

        initialize: function (options) {
            this.setAttributes({
                maxHeight: 800,
                maxWidth : 1200
            });

            this.parent(options);
            this.$Checkout      = null;
            this.$PayToOrderBtn = null;
            this.$isOrdering    = false;

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        $onOpen: function () {
            if (this.$Checkout) {
                return;
            }

            this.$Buttons.addClass('buttons-multiple');

            this.$PayToOrderBtn = new QUIButton({
                'class'  : 'SimpleCheckoutWindow-btn-payToOrder',
                textimage: 'fas fa-shopping-cart',
                disabled : true,
                text     : QUILocale.get('quiqqer/order', 'ordering.btn.pay.to.order'),
                title    : QUILocale.get('quiqqer/order', 'ordering.btn.pay.to.order'),
                events   : {
                    onClick: () => {
                        this.$PayToOrderBtn.disable();

                        this.$Checkout.orderWithCosts().catch((e) => {
                            this.$PayToOrderBtn.enable();
                        });
                    }
                }
            });

            const CancelBtn = new Element('button', {
                'class': 'SimpleCheckoutWindow-btn-cancel',
                html   : QUILocale.get('quiqqer/order-simple-checkout', 'SimpleCheckoutWindow.btn.cancel'),
                events : {
                    click: () => {
                        this.fireEvent('cancel', [this]);
                        this.close();
                    }
                }
            }).inject(this.$Buttons);

            this.addButton(this.$PayToOrderBtn);

            this.Loader.show();
            this.getContent().set('html', '');

            this.$Checkout = new SimpleCheckout({
                products         : this.getAttribute('products'),
                showPayToOrderBtn: false,
                events           : {
                    onLoaded         : () => {
                        this.Loader.hide();
                    },
                    onOrderStart     : () => {
                        CancelBtn.disabled = true;
                        this.$PayToOrderBtn.disable();
                    },
                    onOrderSuccessful: () => {
                        this.$Buttons.removeClass('buttons-multiple');
                        CancelBtn.destroy();
                        this.$PayToOrderBtn.destroy();

                        this.addButton(new QUIButton({
                            'class'  : 'SimpleCheckoutWindow-btn-close',
                            textimage: 'fas fa-check',
                            text     : QUILocale.get('quiqqer/order-simple-checkout', 'SimpleCheckoutWindow.btn.continue'),
                            title    : QUILocale.get('quiqqer/order-simple-checkout', 'SimpleCheckoutWindow.btn.continue'),
                            events   : {
                                onClick: () => {
                                    this.fireEvent('closeOrderSuccessful', [this]);
                                    this.close();
                                }
                            }
                        }));

                        this.getContent().scrollToTop = 0;
                    },
                    onLoadedError    : () => {
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
                    },
                    onOrderValid     : () => {
                        if (this.$isOrdering) {
                            return;
                        }

                        this.$PayToOrderBtn.enable();
                    },
                    onOrderInvalid   : () => {
                        this.$PayToOrderBtn.disable();
                    }
                }
            }).inject(this.getContent());
        }
    });
});
