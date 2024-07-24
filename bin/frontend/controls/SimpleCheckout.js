/**
 * @event onOrderValid [this] - Fires as soon as all order requirements are met
 * @event onOrderInvalid [this] - Fires if the order requirements are not met
 * @event onOrderStart [this] - Fires as soon as the order starts to execute
 * @event onOrderSuccessfull [this] - Fires if the order was successfully executed
 */
define('package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/utils/Form',
    'Locale',
    'Ajax'

], function (QUI, QUIControl, QUILoader, QUIFormUtils, QUILocale, QUIAjax) {
    'use strict';

    const lg = 'quiqqer/order-simple-checkout';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout',

        Binds: [
            'update',
            '$onInject',
            '$onImport',
            'toggleAllProducts',
            'scrollToPayment'
        ],

        options: {
            orderHash           : false,
            loadHashFromUrl     : false,
            showPayToOrderBtn   : true,
            showOrderSuccessInfo: true
        },

        initialize: function (options) {
            this.parent(options);

            this.$Form              = null;
            this.$Delivery          = null;
            this.$Billing           = null;
            this.$Shipping          = null;
            this.$Payment           = null;
            this.Loader             = null;
            this.$PayToOrderBtn     = null;
            this.ScrollToPaymentBtn = null;

            this.showAllProductsBtn = null;

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
            });

            QUI.addEvent('onQuiqqerCurrencyChange', (Instance, curr) => {
                this.setCurrency(curr.code);
            });

            if (!this.getAttribute('orderHash') && window.location.hash) {
                this.setAttribute('orderHash', window.location.hash.replace('#', ''));
            }
        },

        $onImport: function () {
            const Elm = this.getElm();

            this.Loader = new QUILoader().inject(Elm);
            this.$setAnchor();

            if (parseInt(this.getElm().get('data-qui-load-hash-from-url')) === 1) {
                this.setAttribute('loadHashFromUrl', true);
            }

            this.$Form = Elm.getElement('form');

            this.getElm().getElements('a.log-in').addEvent('click', (e) => {
                e.stop();

                require([
                    'package/quiqqer/frontend-users/bin/frontend/controls/login/Window'
                ], (LoginWindow) => {
                    new LoginWindow({
                        redirect: false,
                        reload  : false,
                        events  : {
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
                        redirect : false,
                        reload   : false,
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

            // check order status
            this.$loadOrder().then((orderData) => {
                // orderData === false = no permission for this order
                if (orderData === false) {
                    // normal load
                    window.location.hash = '';
                    this.setAttribute('orderHash', '');

                    return this.$loadGUI();
                }

                if (typeof orderData !== 'undefined' &&
                    typeof orderData.data !== 'undefined' &&
                    orderData.data &&
                    typeof orderData.data.orderedWithCosts !== 'undefined' &&
                    orderData.data.orderedWithCosts
                ) {
                    // show payment step
                    return this.$loadPayment();
                }

                return this.$loadGUI();
            }).then(() => {
                // Terms of Service
                this.getElm().getElements('a[data-project]').addEvent('click', function (e) {
                    let Target = e.target;

                    if (Target.nodeName !== 'A') {
                        Target = Target.getParent('a');
                    }

                    if (!Target.get('data-project') || !Target.get('data-lang') || !Target.get('data-id')) {
                        return;
                    }

                    e.stop();

                    require(['package/quiqqer/controls/bin/site/Window'], function (Win) {
                        new Win({
                            showTitle: true,
                            project  : Target.get('data-project'),
                            lang     : Target.get('data-lang'),
                            id       : Target.get('data-id')
                        }).open();
                    });
                });

                this.Loader.hide();

                moofx([
                    this.getElm().getElements('form'),
                    this.getElm().getElements('.quiqqer-simple-checkout-orderDetails')
                ]).animate({
                    opacity: 1
                });
            });
        },

        $onInject: function () {
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
                    'package/quiqqer/frontend-users/bin/frontend/controls/login/Window'
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

        $loadOrder: function () {
            if (this.getAttribute('orderHash')) {
                return new Promise((resolve, reject) => {
                    QUIAjax.post('package_quiqqer_order-simple-checkout_ajax_frontend_getOrder', resolve, {
                        'package': 'quiqqer/order-simple-checkout',
                        orderHash: this.getAttribute('orderHash'),
                        onError  : reject
                    });
                });
            }

            return Promise.resolve();
        },

        $loadGUI: function () {
            const hideLoader = () => {
                this.Loader.hide();
            };

            const showLoader = () => {
                this.Loader.show();
            };

            let SetCurrency = Promise.resolve();

            if (typeof window.DEFAULT_USER_CURRENCY !== 'undefined' &&
                typeof window.DEFAULT_USER_CURRENCY.code !== 'undefined') {
                SetCurrency = this.setCurrency(window.DEFAULT_USER_CURRENCY.code);
            }

            SetCurrency.then(() => {
                return Promise.all([
                    this.$getControl(this.getElm().getElement('.quiqqer-simple-checkout-delivery')),
                    this.$getControl(this.getElm().getElement('.quiqqer-simple-checkout-shipping')),
                    this.$getControl(this.getElm().getElement('.quiqqer-simple-checkout-payment')),
                    this.$getControl(this.getElm().getElement('.quiqqer-simple-checkout-billing'))
                ]);
            }).then((instances) => {
                this.$Delivery = instances[0];
                this.$Shipping = instances[1];
                this.$Payment  = instances[2];
                this.$Billing  = instances[3];

                if (!this.$Delivery && !this.$Shipping && !this.$Payment && !this.$Billing) {
                    this.Loader.hide();
                    return;
                }

                this.$Delivery.setAttribute('Checkout', this);
                if (this.$Shipping) {
                    this.$Shipping.setAttribute('Checkout', this);
                }
                this.$Payment.setAttribute('Checkout', this);
                if (this.$Billing) {
                    this.$Billing.setAttribute('Checkout', this);
                }

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

                if (this.$Billing) {
                    this.$Billing.addEvent('change', () => {
                        this.Loader.show();
                        this.update().then(hideLoader);
                    });
                }

                this.$PayToOrderBtn = this.getElm().getElement('[name="pay"]');

                if (this.getAttribute('showPayToOrderBtn')) {
                    this.$PayToOrderBtn.addEvent('click', (e) => {
                        e.stop();
                        this.orderWithCosts();
                    });
                } else {
                    this.$PayToOrderBtn.destroy();
                    this.$PayToOrderBtn = null;
                }

                this.ScrollToPaymentBtn = this.getElm().querySelector('.quiqqer-simple-checkout__scrollToPaymentBtn');

                if (this.ScrollToPaymentBtn) {
                    this.ScrollToPaymentBtn.addEvent('click', this.scrollToPayment);
                }

                // load
                this.$Delivery.fireEvent('change');
            }).catch((err) => {
                console.error(err);
                this.Loader.hide();
            });
        },

        $loadPayment: function () {
            return new Promise((resolve, reject) => {
                QUIAjax.post(
                    'package_quiqqer_order-simple-checkout_ajax_frontend_getPaymentStep',
                    (result) => {
                        const Container = this.getElm().getElement('.quiqqer-simple-checkout-container');

                        // for the OrderProcess.js
                        if (this.$Form) {
                            this.$Form.set('data-order-hash', result.orderHash);
                            this.$Form.set('data-products-count', result.productCount);
                        }

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
                                            if (typeof Container.scrollIntoView === 'function') {
                                                Container.scrollIntoView({
                                                    behavior: 'smooth',
                                                    block   : 'center',
                                                    inline  : 'start'
                                                });
                                            }

                                            resolve();
                                        }
                                    });
                                });
                            }
                        });
                    },
                    {
                        'package': 'quiqqer/order-simple-checkout',
                        orderHash: this.getAttribute('orderHash'),
                        onError  : reject
                    }
                );
            });


        },

        $loadProducts: function () {
            if (this.getAttribute('products') && !this.getAttribute('orderHash')) {
                return new Promise((resolve, reject) => {
                    QUIAjax.post(
                        'package_quiqqer_order-simple-checkout_ajax_frontend_newOrderInProcess',
                        (orderHash) => {
                            this.setAttribute('orderHash', orderHash);
                            this.$setAnchor();
                            resolve();
                        },
                        {
                            'package': 'quiqqer/order-simple-checkout',
                            products : JSON.encode(this.getAttribute('products')),
                            onError  : reject
                        }
                    );
                });
            }

            return Promise.resolve();
        },

        $loadCheckout: function () {
            this.$setAnchor();

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

                        resolve();
                    });
                }, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderHash: this.getAttribute('orderHash')
                });
            });
        },

        setCurrency: function (currency) {
            return new Promise((resolve, reject) => {
                QUIAjax.post('package_quiqqer_order-simple-checkout_ajax_frontend_setCurrency', resolve, {
                    'package': 'quiqqer/order-simple-checkout',
                    currency : currency,
                    orderHash: this.getAttribute('orderHash'),
                    onError  : reject
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

        /**
         * @return {Promise<void>}
         */
        orderWithCosts: function () {
            this.Loader.show();

            return this.update().then(() => {
                if (!this.$Form.reportValidity()) {
                    return;
                }

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

                this.fireEvent('orderStart', [this]);

                // execute order
                QUIAjax.post('package_quiqqer_order-simple-checkout_ajax_frontend_orderWithCosts', (result) => {
                    const Container = this.getElm().getElement('.quiqqer-simple-checkout-container');
                    this.setAttribute('orderHash', result.orderHash);
                    this.$setAnchor();

                    // for the OrderProcess.js
                    if (this.getElm().getElement('form')) {
                        this.getElm().getElement('form').set('data-order-hash', result.orderHash);
                        this.getElm().getElement('form').set('data-products-count', result.productCount);
                    }

                    this.fireEvent('orderSuccessful', [this]);

                    if (!this.getAttribute('showOrderSuccessInfo')) {
                        return;
                    }

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

                                        if (typeof Container.scrollIntoView === 'function') {
                                            Container.scrollIntoView({
                                                behavior: 'smooth',
                                                block   : 'center',
                                                inline  : 'start'
                                            });
                                        }

                                        this.fireEvent('showOrderSuccessInfo', [this]);
                                    }
                                });
                            });
                        }
                    });
                }, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderHash: this.getAttribute('orderHash'),
                    onError  : (err) => {
                        if (typeof err.getMessage === 'function') {
                            this.$showError(err.getMessage());
                            this.Loader.hide();
                            return;
                        }

                        console.error(err);
                        this.Loader.hide();
                    }
                });
            });
        },

        $getControl: function (Node) {
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

        $setAnchor: function () {
            if (!this.getAttribute('loadHashFromUrl')) {
                return;
            }

            if (!this.getAttribute('orderHash')) {
                return;
            }

            window.location.hash = this.getAttribute('orderHash');
        },

        $refreshBasket: function () {
            this.Loader.show();

            return new Promise((resolve) => {
                QUIAjax.get('package_quiqqer_order-simple-checkout_ajax_frontend_basket', (basket) => {
                    if (this.getElm().getElement('.quiqqer-simple-checkout-basket__inner')) {
                        this.getElm().getElement('.quiqqer-simple-checkout-basket__inner').set('html', basket);

                        this.showAllProductsBtn = this.getElm().querySelector('.articleList__btnShowMore');

                        if (this.showAllProductsBtn) {
                            this.showAllProductsBtn.addEvent('click', this.toggleAllProducts);
                        }
                    }

                    this.Loader.hide();
                    resolve();
                }, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderHash: this.getAttribute('orderHash')
                });
            });
        },

        $showError: function (message) {
            // @todo michael -> schönere error message
            QUI.getMessageHandler().then((MH) => {
                MH.addError(message);
            });

            console.error(message);
        },

        update: function () {
            return new Promise((resolve) => {
                const orderData = QUIFormUtils.getFormData(this.getElm().getElement('form'));

                QUIAjax.post('package_quiqqer_order-simple-checkout_ajax_frontend_update', (isValid) => {
                    if (isValid) {
                        this.fireEvent('orderValid', [this]);
                    } else {
                        this.fireEvent('orderInvalid', [this]);
                    }

                    const Delivery = this.getElm().getElement('.quiqqer-simple-checkout-data-delivery');
                    const Shipping = this.getElm().getElement('.quiqqer-simple-checkout-data-shipping');
                    const Payment  = this.getElm().getElement('.quiqqer-simple-checkout-data-payment');

                    if (!isValid) {
                        QUIAjax.get('package_quiqqer_order-simple-checkout_ajax_frontend_validate', (missing) => {
                            if (Delivery) {
                                if (missing.indexOf('address') !== -1) {
                                    Delivery.addClass('quiqqer-simple-checkout-require');
                                } else {
                                    Delivery.removeClass('quiqqer-simple-checkout-require');
                                }
                            }

                            if (Shipping) {
                                if (missing.indexOf('shipping') !== -1) {
                                    Shipping.addClass('quiqqer-simple-checkout-require');
                                } else {
                                    Shipping.removeClass('quiqqer-simple-checkout-require');
                                }
                            }

                            if (Payment) {
                                if (missing.indexOf('payment') !== -1) {
                                    Payment.addClass('quiqqer-simple-checkout-require');
                                } else {
                                    Payment.removeClass('quiqqer-simple-checkout-require');
                                }
                            }
                        }, {
                            'package': 'quiqqer/order-simple-checkout',
                            orderData: JSON.encode(orderData),
                            orderHash: this.getAttribute('orderHash')
                        });
                    } else {
                        if (Delivery) {
                            Delivery.removeClass('quiqqer-simple-checkout-require');
                        }

                        if (Shipping) {
                            Shipping.removeClass('quiqqer-simple-checkout-require');
                        }

                        if (Payment) {
                            Payment.removeClass('quiqqer-simple-checkout-require');
                        }
                    }

                    this.$refreshBasket().then(resolve);
                }, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderData: JSON.encode(orderData),
                    orderHash: this.getAttribute('orderHash'),
                    onError  : (err) => {
                        if (typeof err.getMessage === 'function') {
                            this.$showError(err.getMessage());
                            this.Loader.hide();
                            resolve();
                            return;
                        }

                        console.error(err);
                        this.Loader.hide();
                        resolve();
                    }
                });
            });
        },

        /**
         * Show or hide all products
         *
         * @param event
         */
        toggleAllProducts: function (event) {
            event.stop();

            const Elm            = this.getElm();
            const HiddenList     = Elm.querySelector('.articleList__hidden'),
                  InnerContainer = Elm.querySelector('.articleList__hiddenInner');

            if (!HiddenList || !InnerContainer) {
                return;
            }

            if (HiddenList.offsetHeight > 0) {
                this.hideHiddenArticles(HiddenList);
                const max = this.showAllProductsBtn.getAttribute('data-qui-max');

                this.showAllProductsBtn.innerHTML = QUILocale.get(lg, 'ordering.btn.showAllProductsText.open', {
                    max: max ? max : ''
                });

                return;
            }

            this.showHiddenArticles(HiddenList, InnerContainer);

            this.showAllProductsBtn.innerHTML = QUILocale.get(lg, 'ordering.btn.showAllProductsText.close');
        },

        /**
         * Show hidden products
         *
         * @param ListNode
         * @param InnerNode
         */
        showHiddenArticles: function (ListNode, InnerNode) {
            moofx(ListNode).animate({
                height : InnerNode.offsetHeight,
                opacity: 1
            }, {
                callback: () => {
                    ListNode.style.height = null;
                }
            });
        },

        /**
         * Hide products
         *
         * @param ListNode
         */
        hideHiddenArticles: function (ListNode) {
            moofx(ListNode).animate({
                height : 0,
                opacity: 0
            });
        },

        /**
         * Scroll to the payment section.
         * If some requirements are missing, scroll to the next missing label.
         *
         * @param event
         */
        scrollToPayment: function (event) {
            event.stop();

            const Elm           = this.getElm();
            const SumNode       = Elm.querySelector('.articles-sum-container');
            const RequiredField = Elm.querySelector('.quiqqer-simple-checkout-require');

            if (RequiredField) {
                RequiredField.scrollIntoView({behavior: "smooth"});
                return;
            }

            SumNode.scrollIntoView({behavior: "smooth"});
        }
    });
});
