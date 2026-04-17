/**
 * @event onOrderValid [this] - Fires as soon as all order requirements are met
 * @event onOrderInvalid [this] - Fires if the order requirements are not met
 * @event onOrderStart [this] - Fires as soon as the order starts to execute
 * @event onOrderSuccessful [this] - Fires if the order was successfully executed
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
        Type: 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout',

        Binds: [
            'update',
            '$onInject',
            '$onImport',
            'toggleAllProducts',
            'scrollToPayment'
        ],

        options: {
            orderHash: false,
            loadHashFromUrl: false,
            showPayToOrderBtn: true,
            showEmail: false,
            showOrderSuccessInfo: true,
            showBasketLink: true,
            disableAddress: false,
            disableProductLinks: 'default'
        },

        initialize: function (options) {
            this.parent(options);

            this.$Form = null;
            this.$Delivery = null;
            this.$Billing = null;
            this.$Shipping = null;
            this.$Payment = null;

            this.$BasketLoader = null;
            this.Loader = null;

            this.$initialized = false;
            this.$isOrdering = false;
            this.$orderPromise = null;

            this.$PayToOrderBtn = null;
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
                const hash = window.location.hash.replace(/^#/, '');
                const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

                // check if hash is a valid uuid
                if (uuidRegex.test(hash)) {
                    this.setAttribute('orderHash', hash);
                }
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

            if (this.getElm().getElement('[data-qui="package/quiqqer/order/bin/frontend/controls/orderProcess/Login"]')) {
                return;
            }

            this.Loader.show();

            this.$BasketLoader = new Element('span', {
                'class': 'fa fa-spin fa-circle-notch simpleCheckout-details-section-loader'
            }).inject(
                this.getElm().getElement('.quiqqer-simple-checkout-basket')
            );

            const urlParams = new URLSearchParams(window.location.search);
            const product = urlParams.get('product');
            let loaded;

            if (product) {
                loaded = this.$loadProducts().then(() => {
                    return this.$loadOrder();
                });
            } else {
                loaded = this.$loadOrder();
            }


            // check order status
            loaded.then((data) => {
                const orderData = data.order;

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
                if (this.getElm().getElement('.quiqqer-order-step-processing')) {
                    // processing step
                    if (this.getElm().getElement('.quiqqer-simple-checkout-orderDetails')) {
                        this.getElm().getElement('.quiqqer-simple-checkout-orderDetails').setStyle('display', 'none');
                    }

                    if (this.getElm().getElement('.quiqqer-simple-checkout__scrollToPaymentContainer')) {
                        this.getElm().getElement('.quiqqer-simple-checkout__scrollToPaymentContainer').setStyle('display', 'none');
                    }
                }

                this.$parseTermsAndConditions();
                this.Loader.hide();

                moofx([
                    this.getElm().getElements('form'),
                    this.getElm().getElements('.quiqqer-simple-checkout-orderDetails'),
                    this.getElm().getElements('.quiqqer-simple-checkout__scrollToPaymentContainer'),
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
                        onError: reject
                    });
                });
            }

            if (!this.getAttribute('products') && !this.getAttribute('orderHash')) {
                // load from basket
                return new Promise((resolve) => {
                    require(['package/quiqqer/order/bin/frontend/Basket'], (Basket) => {
                        if (!Basket.isLoaded()) {
                            Basket.addEvent('load', () => {
                                resolve(Basket);
                            });
                        } else {
                            resolve(Basket);
                        }
                    });
                }).then((Basket) => {
                    return Basket.toOrder();
                }).then((orderHash) => {
                    this.setAttribute('orderHash', orderHash);

                    return this.$loadOrder();
                });
            }

            return Promise.resolve({
                order: false
            });
        },

        $loadGUI: function () {
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
                this.$Payment = instances[2];
                this.$Billing = instances[3];

                if (!this.$Delivery && !this.$Shipping && !this.$Payment && !this.$Billing) {
                    this.Loader.hide();
                    return;
                }

                const steps = [this.$Delivery, this.$Shipping, this.$Payment, this.$Billing];

                steps.forEach(step => {
                    if (step) {
                        step.setAttribute('Checkout', this);
                    }
                });


                if (this.$Delivery) {
                    this.$Delivery.addEvent('change', () => {
                        this.update().then(() => {
                            if (this.$Shipping) {
                                return this.$Shipping.refresh().then(() => {
                                    return this.$Payment.refresh();
                                });
                            } else {
                                return this.$Payment.refresh();
                            }
                        });
                    });
                }

                if (this.$Shipping) {
                    this.$Shipping.addEvent('change', () => {
                        this.update().then(() => {
                            return this.$Payment.refresh();
                        });
                    });
                }

                if (this.$Payment) {
                    this.$Payment.addEvent('change', () => {
                        this.update();
                    });
                }

                if (this.$Billing) {
                    this.$Billing.addEvent('change', () => {
                        this.update();
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
                const firstAvailable = steps.find(step => step);
                if (firstAvailable) {
                    firstAvailable.fireEvent('change');
                }
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

                        this.setAttribute('orderHash', result.orderHash);

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
                                                    block: 'center',
                                                    inline: 'start'
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
                        onError: reject
                    }
                );
            });
        },

        $loadProducts: function () {
            const urlParams = new URLSearchParams(window.location.search);
            const product = urlParams.get('product');

            // @todo url params - product
            if (product) {
                this.setAttribute('products', [
                    {
                        id: product,
                        quantity: 1
                    }
                ]);
            }

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
                            products: JSON.encode(this.getAttribute('products')),
                            onError: reject
                        }
                    );
                });
            }

            return Promise.resolve();
        },

        $loadCheckout: function () {
            this.$setAnchor();

            return new Promise((resolve) => {
                const settings = this.getAttributes();
                settings.showEmail = this.getAttribute('showEmail');

                QUIAjax.get('package_quiqqer_order-simple-checkout_ajax_frontend_getSimpleCheckoutControl', (html) => {
                    const Ghost = new Element('div', {
                        html: html
                    });

                    const Checkout = Ghost.getElement('[data-name="quiqqer-simple-checkout"]');

                    this.getElm().addClass(Checkout.className);
                    this.getElm().set('data-qui', Checkout.get('data-qui'));
                    this.getElm().set('data-name', 'quiqqer-simple-checkout');
                    this.getElm().set('html', Checkout.get('html'));
                    Ghost.getElements('style').inject(this.getElm());

                    QUI.parse(this.getElm()).then(() => {
                        this.fireEvent('loaded', [this]);
                        this.$onImport();

                        resolve();
                    });
                }, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderHash: this.getAttribute('orderHash'),
                    settings: JSON.encode(settings)
                });
            });
        },

        setCurrency: function (currency) {
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

        validate: function () {
            const scrollToFirstInvalidField = () => {
                const firstInvalid = this.$Form.querySelector(':invalid');

                setTimeout(() => {
                    firstInvalid.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    firstInvalid.focus();
                }, 200);
            }

            // check country, because input country is not focusable
            if (typeof this.$Form.elements.country !== 'undefined') {
                const Countries = QUI.Controls.getById(this.$Form.elements.country.get('data-quiid'));

                if (this.$Form.elements.country.value === '') {
                    this.$Form.elements.country.value = Countries.getValue();
                }

                if (this.$Form.elements.country.value === '') {
                    Countries.focus();
                    return false;
                }
            }

            if (!this.$Form.reportValidity()) {
                scrollToFirstInvalidField();
                return false;
            }

            const required = Array.from(this.getElm().querySelectorAll('[required]'));
            let i, len, requireField;

            for (i = 0, len = required.length; i < len; i++) {
                requireField = required[i];

                if ('checkValidity' in requireField) {
                    if (requireField.checkValidity() === false) {
                        requireField.reportValidity();
                        scrollToFirstInvalidField();
                        return false;
                    }
                }

                if (requireField.type === 'radio') {
                    const radios = this.getElm().querySelectorAll(`[name="${requireField.name}"]`);
                    let checked = false;

                    radios.forEach(radio => {
                        if (radio.checked) checked = true;
                    });

                    if (!checked) {
                        scrollToFirstInvalidField();
                        return false;
                    }

                    continue;
                }

                if (requireField.type === 'checkbox') {
                    if (!requireField.checked) {
                        scrollToFirstInvalidField();
                        return false;
                    }
                }

                if (requireField.value === '') {
                    scrollToFirstInvalidField();
                    return false;
                }
            }

            return true;
        },

        /**
         * @return {Promise<void>}
         */
        orderWithCosts: function () {
            if (this.$isOrdering) {
                return this.$orderPromise || Promise.resolve();
            }

            if (this.validate() === false) {
                this.fireEvent('orderInvalid', [this]);
                return Promise.reject();
            }

            this.$isOrdering = true;
            this.$setPayButtonDisabled(true);
            this.Loader.show();

            this.$orderPromise = this.update().then(() => {
                this.fireEvent('orderStart', [this]);

                return new Promise((resolve, reject) => {
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

                        if (this.getElm().getElement('.quiqqer-simple-checkout-orderDetails')) {
                            this.getElm().getElement('.quiqqer-simple-checkout-orderDetails').setStyle('display', 'none');
                        }

                        if (this.getElm().getElement('.quiqqer-simple-checkout__scrollToPaymentContainer')) {
                            this.getElm().getElement('.quiqqer-simple-checkout__scrollToPaymentContainer').setStyle('display', 'none');
                        }

                        this.fireEvent('orderSuccessful', [this]);
                        const scripts = [];
                        const Ghost = new Element('div', {
                            html: result.html
                        });

                        // trigger js stuff
                        Ghost.getElements('script').forEach(function (Script) {
                            const New = new Element('script');

                            if (Script.get('html')) {
                                New.set('html', Script.get('html'));
                            }

                            if (Script.get('src')) {
                                New.set('src', Script.get('src'));
                            }

                            scripts.push(New);
                        });

                        if (!this.getAttribute('showOrderSuccessInfo')) {
                            scripts.forEach((Script) => {
                                Script.inject(Container);
                            });

                            this.Loader.hide();
                            resolve();
                            return;
                        }

                        moofx(Container).animate({
                            opacity: 0
                        }, {
                            callback: () => {
                                Container.set('html', result.html);

                                scripts.forEach((Script) => {
                                    Script.inject(Container);
                                });

                                QUI.parse(Container).then(() => {
                                    moofx(Container).animate({
                                        opacity: 1
                                    }, {
                                        callback: () => {
                                            this.Loader.hide();

                                            if (typeof Container.scrollIntoView === 'function') {
                                                Container.scrollIntoView({
                                                    behavior: 'smooth',
                                                    block: 'center',
                                                    inline: 'start'
                                                });
                                            }

                                            this.fireEvent('showOrderSuccessInfo', [this]);
                                            resolve();
                                        }
                                    });
                                }).catch(reject);
                            }
                        });
                    }, {
                        'package': 'quiqqer/order-simple-checkout',
                        orderHash: this.getAttribute('orderHash'),
                        onError: reject
                    });
                });
            });

            return this.$orderPromise.catch((err) => {
                if (typeof err.getMessage === 'function') {
                    this.$showError(err.getMessage());
                } else {
                    console.error(err);
                }

                this.$isOrdering = false;
                this.$orderPromise = null;
                this.$setPayButtonDisabled(false);
                this.Loader.hide();

                throw err;
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

            if (window.location.hash !== '') {
                // hash besteht, nicht überschreiben
                return;
            }

            window.location.hash = this.getAttribute('orderHash');
        },

        $parseTermsAndConditions: function () {
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
                        project: Target.get('data-project'),
                        lang: Target.get('data-lang'),
                        id: Target.get('data-id')
                    }).open();
                });
            });
        },

        $refreshBasket: function () {
            //this.Loader.show();
            this.$BasketLoader.style.display = '';

            return new Promise((resolve) => {
                QUIAjax.get('package_quiqqer_order-simple-checkout_ajax_frontend_basket', (basket) => {
                    const Ghost = new Element('div', {
                        html: basket
                    });

                    const basketCss = '.quiqqer-simple-checkout-basket__inner';
                    const noticeCss = '.quiqqer-order-step-checkout-notice';
                    const mobileBasketCss = '.quiqqer-simple-checkout-orderDetails';

                    if (this.getElm().querySelector(basketCss)) {
                        this.getElm().querySelector(basketCss).set('html', Ghost.querySelector(basketCss).innerHTML);

                        this.showAllProductsBtn = this.getElm().querySelector('.articleList__btnShowMore');

                        if (this.showAllProductsBtn) {
                            this.showAllProductsBtn.addEvent('click', this.toggleAllProducts);
                        }
                    }

                    if (this.getElm().querySelector(mobileBasketCss)) {
                        this.getElm().querySelector(mobileBasketCss).set('html', Ghost.querySelector(mobileBasketCss).innerHTML);
                    }

                    if (this.getElm().querySelector(noticeCss)) {
                        const Notice = this.getElm().querySelector(noticeCss);
                        const inputs = Notice.querySelectorAll('input');

                        Notice.set('html', Ghost.querySelector(noticeCss).innerHTML);
                        this.$parseTermsAndConditions();

                        inputs.forEach((node) => {
                            const NewNode = this.getElm().querySelector('[name="' + node.name + '"]');

                            if (node.type === 'radio' || node.type === 'checkbox') {
                                NewNode.checked = node.checked;
                                return;
                            }

                            NewNode.value = node.value;
                        });
                    }

                    if (!this.$isOrdering) {
                        this.Loader.hide();
                    }
                    this.$BasketLoader.style.display = 'none';
                    resolve();
                }, {
                    'package': 'quiqqer/order-simple-checkout',
                    orderHash: this.getAttribute('orderHash'),
                    settings: JSON.encode(this.getAttributes())
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

        $setPayButtonDisabled: function (disabled) {
            if (!this.$PayToOrderBtn) {
                return;
            }

            this.$PayToOrderBtn.disabled = disabled;

            if (disabled) {
                this.$PayToOrderBtn.setAttribute('disabled', 'disabled');
                return;
            }

            this.$PayToOrderBtn.removeAttribute('disabled');
        },

        update: function () {
            if (!this.$initialized) {
                this.$initialized = true;
                return Promise.resolve();
            }

            this.$BasketLoader.style.display = '';

            return new Promise((resolve) => {
                const Form = this.getElm().getElement('form');
                const orderData = QUIFormUtils.getFormData(Form);

                // because of disabled
                if (Form.elements['country']) {
                    orderData.country = QUI.Controls.getById(Form.elements['country'].get('data-quiid')).getValue();
                }

                QUIAjax.post('package_quiqqer_order-simple-checkout_ajax_frontend_update', (isValid) => {
                    if (isValid) {
                        this.fireEvent('orderValid', [this]);
                    } else {
                        this.fireEvent('orderInvalid', [this]);
                    }

                    const Delivery = this.getElm().getElement('.quiqqer-simple-checkout-data-delivery');
                    const Shipping = this.getElm().getElement('.quiqqer-simple-checkout-data-shipping');
                    const Payment = this.getElm().getElement('.quiqqer-simple-checkout-data-payment');

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
                    onError: (err) => {
                        if (typeof err.getMessage === 'function') {
                            this.$showError(err.getMessage());
                            if (!this.$isOrdering) {
                                this.Loader.hide();
                            }
                            resolve();
                            return;
                        }

                        console.error(err);
                        if (!this.$isOrdering) {
                            this.Loader.hide();
                        }
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

            const Elm = this.getElm();
            const HiddenList = Elm.querySelector('.articleList__hidden'),
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
                height: InnerNode.offsetHeight,
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
                height: 0,
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

            const Elm = this.getElm();
            const SumNode = Elm.querySelector('.articles-sum-container');
            const RequiredField = Elm.querySelector('.quiqqer-simple-checkout-require');

            if (RequiredField) {
                RequiredField.scrollIntoView({behavior: 'smooth'});
                return;
            }

            SumNode.scrollIntoView({behavior: 'smooth'});
        }
    });
});
