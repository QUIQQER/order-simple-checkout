define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/utils/Form',
    'Ajax'

], function(QUI, QUIControl, QUILoader, QUIFormUtils, QUIAjax) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout',

        Binds: [
            'update',
            '$onInject',
            '$onImport'
        ],

        options: {
            orderHash: false
        },

        initialize: function(options) {
            this.parent(options);

            this.$Delivery = null;
            this.$Shipping = null;
            this.$Payment = null;
            this.Loader = null;

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
            });

            QUI.addEvent('onQuiqqerCurrencyChange', (Instance, curr) => {
                this.setCurrency(curr.code);
            });
        },

        $onImport: function() {
            this.Loader = new QUILoader().inject(this.getElm());

            const hideLoader = () => {
                this.Loader.hide();
            };
            const showLoader = () => {
                this.Loader.show();
            };

            this.getElm().getElements('a.log-in').addEvent('click', (e) => {
                e.stop();

                require([
                    'package/quiqqer/frontend-users/bin/frontend/controls/login/Window'
                ], (LoginWindow) => {
                    new LoginWindow({
                        redirect: false,
                        reload: false,
                        events: {
                            onSuccess: () => {
                                window.location.reload();
                            }
                        }
                    }).open();
                });
            });

            const LoginNode = this.getElm().getElement('.quiqqer-order-simple-login');

            if (LoginNode) {
                require(['package/quiqqer/frontend-users/bin/frontend/controls/login/Login'], (Login) => {
                    new Login({
                        redirect: false,
                        reload: false,
                        onSuccess: () => {
                            this.getElm().setStyle('minHeight', this.getElm().getSize().y);
                            this.Loader.show();
                            LoginNode.destroy();
                            this.$onInject();
                        }
                    }).inject(LoginNode);
                });

                return;
            }

            this.Loader.show();

            let SetCurrency = Promise.resolve();

            if (typeof window.DEFAULT_USER_CURRENCY !== 'undefined' &&
                typeof window.DEFAULT_USER_CURRENCY.code !== 'undefined') {
                SetCurrency = this.setCurrency(window.DEFAULT_USER_CURRENCY.code);
            }

            SetCurrency.then(() => {
                return Promise.all([
                    this.$getControl(this.getElm().getElement('.quiqqer-simple-checkout-delivery')),
                    this.$getControl(this.getElm().getElement('.quiqqer-simple-checkout-shipping')),
                    this.$getControl(this.getElm().getElement('.quiqqer-simple-checkout-payment'))
                ]);
            }).then((instances) => {
                this.$Delivery = instances[0];
                this.$Shipping = instances[1];
                this.$Payment = instances[2];

                if (!this.$Delivery && !this.$Shipping && !this.$Payment) {
                    this.Loader.hide();
                    return;
                }

                this.$Delivery.setAttribute('Checkout', this);
                this.$Shipping.setAttribute('Checkout', this);
                this.$Payment.setAttribute('Checkout', this);

                this.$Payment.addEvent('refreshBegin', showLoader);
                this.$Payment.addEvent('refreshEnd', hideLoader);

                this.$Delivery.addEvent('change', () => {
                    this.Loader.show();

                    this.update().then(() => {
                        if (this.$Shipping) {
                            return this.$Shipping.refresh().then(() => {
                                return this.$Payment.refresh();
                            }).then(hideLoader);
                        }

                        this.$Payment.refresh().then(hideLoader);
                    });
                });

                if (this.$Shipping) {
                    this.$Shipping.addEvent('change', () => {
                        this.Loader.show();

                        this.update().then(() => {
                            return this.$Payment.refresh();
                        }).then(hideLoader);
                    });
                }

                this.$Payment.addEvent('change', () => {
                    this.Loader.show();
                    this.update().then(hideLoader);
                });

                this.getElm().getElement('[name="pay"]').addEvent('click', (e) => {
                    e.stop();
                    this.orderWithCosts();
                });

                this.$setSpacingOnMobile();

                // load
                this.$Delivery.fireEvent('change');
            }).catch((err) => {
                console.error(err);
                this.Loader.hide();
            });
        },

        $onInject: function() {
            this.$loadProducts().then(() => {
                return this.$loadCheckout();
            }).catch((err) => {
                console.error(err);
                this.getElm().set('html', '');

                if (this.getElm().getParent('.qui-window-popup')) {
                    this.fireEvent('loadedError', [this]);
                    return;
                }

                require([
                    'package/quiqqer/frontend-users/bin/frontend/controls/login/Login'
                ], (Login) => {
                    new Login({
                        events: {
                            onSuccess: () => {

                            }
                        }
                    }).open();
                });

            });
        },

        $loadProducts: function() {
            if (this.getAttribute('products') && !this.getAttribute('orderHash')) {
                return new Promise((resolve, reject) => {
                    QUIAjax.post(
                        'package_quiqqer_order-simple-checkout_ajax_frontend_newOrderInProcess',
                        (orderHash) => {
                            this.setAttribute('orderHash', orderHash);
                            resolve();
                        },
                        {
                            'package': 'quiqqer/order-simple-checkout',
                            products: JSON.encode(this.getAttribute('products')),
                            onError: reject
                        }
                    );
                });
            }

            return Promise.resolve();
        },

        $loadCheckout: function() {
            return new Promise((resolve) => {
                QUIAjax.get('package_quiqqer_order-simple-checkout_ajax_frontend_getSimpleCheckoutControl', (html) => {
                    const Ghost = new Element('div', {
                        html: html
                    });

                    const Checkout = Ghost.getElement('.quiqqer-simple-checkout');

                    this.getElm().addClass(Checkout.className);
                    this.getElm().set('data-qui', Checkout.get('data-qui'));
                    this.getElm().set('html', Checkout.get('html'));
                    Ghost.getElements('style').inject(this.getElm());

                    QUI.parse(this.getElm()).then(() => {
                        this.fireEvent('loaded', [this]);
                        this.$onImport();
                        this.$setSpacingOnMobile();

                        resolve();
                    });
                }, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderHash: this.getAttribute('orderHash')
                });
            });
        },

        setCurrency: function(currency) {
            return new Promise((resolve, reject) => {
                QUIAjax.post('package_quiqqer_order-simple-checkout_ajax_frontend_setCurrency', resolve, {
                    'package': 'quiqqer/order-simple-checkout',
                    currency: currency,
                    orderHash: this.getAttribute('orderHash'),
                    onError: reject
                });
            }).then(() => {
                return this.$refreshBasket();
            }).then(() => {
                if (this.$Shipping) {
                    return this.$Shipping.refresh();
                }
            }).then(() => {
                return this.$Payment.refresh();
            }).catch(() => {
            });
        },

        orderWithCosts: function() {
            this.Loader.show();

            this.update().then(() => {
                this.Loader.show();
                const Terms = this.getElm().getElement('[name="termsAndConditions"]');

                if (!Terms.checked) {
                    this.Loader.hide();

                    if ('reportValidity' in Terms) {
                        Terms.reportValidity();

                        if ('checkValidity' in Terms) {
                            if (Terms.checkValidity() === false) {
                                return;
                            }
                        }
                    }

                    return;
                }

                // execute order
                QUIAjax.post('package_quiqqer_order-simple-checkout_ajax_frontend_orderWithCosts', (result) => {
                    const Container = this.getElm().getElement('.quiqqer-simple-checkout-container');

                    moofx(Container).animate({
                        opacity: 0
                    }, {
                        callback: () => {
                            Container.set('html', result.html);

                            QUI.parse(Container).then(() => {
                                moofx(Container).animate({
                                    opacity: 1
                                }, {
                                    callback: () => {
                                        this.Loader.hide();
                                    }
                                });
                            });
                        }
                    });
                }, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderHash: this.getAttribute('orderHash')
                });
            });
        },

        $getControl: function(Node) {
            return new Promise((resolve) => {
                if (!Node || !Node.get('data-qui')) {
                    return resolve(null);
                }

                if (Node.get('data-quiid')) {
                    resolve(QUI.Controls.getById(Node.get('data-quiid')));
                    return;
                }

                Node.addEvent('load', () => {
                    resolve(QUI.Controls.getById(Node.get('data-quiid')));
                });
            }).then((Instance) => {
                if (!Instance) {
                    return null;
                }

                Instance.setAttribute('Checkout', this);

                return Instance;
            });
        },

        $refreshBasket: function() {
            this.Loader.show();

            return new Promise((resolve) => {
                QUIAjax.get('package_quiqqer_order-simple-checkout_ajax_frontend_basket', (basket) => {
                    this.getElm().getElement('.quiqqer-simple-checkout-basket__inner').set('html', basket);
                    this.Loader.hide();
                    resolve();
                }, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderHash: this.getAttribute('orderHash')
                });
            });
        },

        update: function() {
            const PayButton = this.getElm().getElement('[name="pay"]');

            return new Promise((resolve) => {
                const orderData = QUIFormUtils.getFormData(this.getElm().getElement('form'));

                QUIAjax.post('package_quiqqer_order-simple-checkout_ajax_frontend_update', (isValid) => {
                    PayButton.disabled = !isValid;

                    this.$refreshBasket().then(resolve);
                }, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderData: JSON.encode(orderData),
                    orderHash: this.getAttribute('orderHash')
                });
            });
        },

        /**
         * Calculate needed margin on mobile.
         * Not pretty solution, needs to be reworked.
         */
        $setSpacingOnMobile: function() {
            if (QUI.getBodySize().x >= 768) {
                return;
            }

            const PayContainer = this.getElm().querySelector('.quiqqer-simple-checkout-data-pay');

            if (!PayContainer) {
                return;
            }

            this.getElm().setStyle('margin-bottom', PayContainer.offsetHeight + 'px');
        }
    });
});
