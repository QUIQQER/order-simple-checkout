<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Basket\ExceptionBasketNotFound;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\OrderInterface;
use QUI\ERP\Order\Settings;
use QUI\ERP\Order\SimpleCheckout\Steps\CheckoutBillingAddress;
use QUI\ERP\Order\SimpleCheckout\Steps\CheckoutDelivery;
use QUI\ERP\Order\SimpleCheckout\Steps\CheckoutPayment;
use QUI\ERP\Order\SimpleCheckout\Steps\CheckoutShipping;
use QUI\ERP\Order\Utils\OrderProcessSteps;
use QUI\Exception;

use function class_exists;
use function class_implements;
use function dirname;
use function file_exists;
use function in_array;

/**
 * Class Checkout
 *
 * Represents the simple checkout control
 */
class Checkout extends QUI\Control
{
    /**
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes([
            'orderHash' => false,
            'template' => false,
            'disableAddress' => false,
            'disableProductLinks' => 'default',
            'showBasketLink' => true,
            'showEmail' => false,
            'data-qui' => 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout',
            'data-name' => 'quiqqer-simple-checkout',
            'data-qui-load-hash-from-url' => 0
        ]);

        $this->addCSSClass('quiqqer-simple-checkout');
        $this->addCSSFile(dirname(__FILE__) . '/Checkout.css');

        parent::__construct($attributes);

        // default
        if ($this->getAttribute('disableProductLinks') === 'default') {
            try {
                $defaultValue = (bool)QUI::getPackage('quiqqer/order-simple-checkout')
                    ->getConfig()->getValue('orderSimpleCheckout', 'disableProductLinks');

                $this->setAttribute('disableProductLinks', $defaultValue);
            } catch (QUI\Exception) {
            }
        }
    }

    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $template = dirname(__FILE__) . '/Checkout.html';

        if ($this->getAttribute('template') && file_exists($this->getAttribute('template'))) {
            $template = $this->getAttribute('template');
        }

        // guest order
        if (QUI::getUsers()->isNobodyUser($this->getUser())) {
            $DefaultOrderProcess = new QUI\ERP\Order\OrderProcess();

            return $DefaultOrderProcess->create();
        }

        // put the basket articles to the order in process, if the current order has no articles
        if (!$this->getOrder()?->getArticles()->count()) {
            try {
                $Basket = QUI\ERP\Order\Handler::getInstance()->getBasketFromUser($this->getUser());
            } catch (QUI\Exception) {
                $Basket = QUI\ERP\Order\Factory::getInstance()->createBasket($this->getUser());
            }

            if ($this->getOrder()) {
                $Basket->toOrder($this->getOrder());
            }
        }

        $Checkout = new QUI\ERP\Order\Controls\OrderProcess\Checkout();
        $Checkout->generateCheckboxLinks($Engine);


        $BasketForHeader = new Basket($this);
        $BasketForHeader->setAttribute('basketForHeader', true);

        $isShippingInstalled = QUI::getPackageManager()->isInstalled('quiqqer/shipping');

        // Basket
        $BasketSite = null;

        if ($this->getAttribute('showBasketLink')) {
            $Project = QUI::getRewrite()->getProject();

            $basketSites = $Project->getSites([
                'where' => [
                    'type' => 'quiqqer/order:types/shoppingCart'
                ],
                'limit' => 1
            ]);

            if (!empty($basketSites)) {
                $BasketSite = $basketSites[0];
            }
        }

        $showDelivery = true;
        $showShipping = true;
        $showBillingAddress = true;

        if (!$isShippingInstalled) {
            $showShipping = false;
            $showBillingAddress = false;
        }

        QUI::getEvents()->fireEvent(
            'onQuiqqerSimpleCheckoutBodyEnd',
            [$this, &$showDelivery, &$showShipping, &$showBillingAddress]
        );

        $Engine->assign([
            'this' => $this,
            'Order' => $this->getOrder(),
            'Basket' => new Basket($this),
            'BasketForHeader' => $BasketForHeader,
            'User' => $this->getUser(),
            'Delivery' => $showDelivery ? new CheckoutDelivery($this) : null, // @phpstan-ignore-line
            'BillingAddress' => $showBillingAddress ? new CheckoutBillingAddress($this) : null,
            'Shipping' => $showShipping ? new CheckoutShipping($this) : null,
            'Payment' => new CheckoutPayment($this),
            'BasketSite' => $BasketSite
        ]);

        return $Engine->fetch($template);
    }

    /**
     * Check if the order is valid and the order can be executed
     *
     * @return bool Returns true if the order is valid, false otherwise.
     */
    public function isValid(): bool
    {
        $validateAddress = true;
        $validateShipping = true;
        $isShippingInstalled = QUI::getPackageManager()->isInstalled('quiqqer/shipping');

        if (!$isShippingInstalled) {
            $validateShipping = false;
        }

        if (class_exists('QUI\ERP\Accounting\Invoice\Utils\Invoice')) {
            $validateAddress = QUI\ERP\Accounting\Invoice\Utils\Invoice::addressRequirement();
        }

        QUI::getEvents()->fireEvent(
            'onQuiqqerSimpleCheckoutValidation',
            [$this, &$validateAddress, &$validateShipping]
        );


        // check address
        try {
            $Order = $this->getOrder();

            if ($validateAddress) {
                if (!$Order) {
                    return false;
                }

                QUI\ERP\Order\Controls\OrderProcess\CustomerData::validateAddress(
                    $Order->getInvoiceAddress()
                );
            }
        } catch (QUI\Exception) {
            return false;
        }

        // check payment
        $Payment = $Order->getPayment();

        if (!$Payment) {
            return false;
        }

        // check shipping
        if ($validateShipping && !$Order->getShipping()) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string>
     */
    public function gatherMissingOrderDetails(): array
    {
        $missing = [];
        $Order = null;

        // check address
        $addressRequired = true;

        if (class_exists('QUI\ERP\Accounting\Invoice\Utils\Invoice')) {
            $addressRequired = QUI\ERP\Accounting\Invoice\Utils\Invoice::addressRequirement();
        }

        try {
            $Order = $this->getOrder();

            if ($Order?->getInvoiceAddress()) {
                QUI\ERP\Order\Controls\OrderProcess\CustomerData::validateAddress(
                    $Order->getInvoiceAddress()
                );
            } else {
                if ($addressRequired) {
                    $missing[] = 'address';
                }
            }
        } catch (QUI\Exception) {
            if ($addressRequired) {
                $missing[] = 'address';
            }
        }

        if (!$Order) {
            $missing[] = 'payment';

            if (QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
                $missing[] = 'shipping';
            }
        } else {
            $Payment = $Order->getPayment();

            if (!$Payment) {
                $missing[] = 'payment';
            }

            if (QUI::getPackageManager()->isInstalled('quiqqer/shipping') && !$Order->getShipping()) {
                $missing[] = 'shipping';
            }
        }

        return $missing;
    }

    /**
     * @return mixed[]
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Permissions\Exception
     * @throws Exception
     */
    public function orderWithCosts(): array
    {
        $OrderInProcess = $this->getOrder();

        if (!$OrderInProcess) {
            throw new QUI\ERP\Order\Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                QUI\ERP\Order\Handler::ERROR_ORDER_NOT_FOUND
            );
        }

        $failedPaymentProcedure = Settings::getInstance()->get('order', 'failedPaymentProcedure');
        $Payment = $OrderInProcess->getPayment();

        if ($failedPaymentProcedure === 'execute') {
            $Order = $OrderInProcess->createOrder(QUI::getUsers()->getSystemUser());
            $Order->setData('orderedWithCosts', true);

            if ($Payment) {
                $Order->setData('orderedWithCostsPayment', $Payment->getId());
            }

            $Order->save(QUI::getUsers()->getSystemUser());

            QUI::getSession()->set(
                'termsAndConditions-' . $Order->getUUID(),
                1
            );

            $this->setAttribute('orderHash', $Order->getUUID());
        } else {
            $OrderInProcess->setData('orderedWithCosts', true);
            $OrderInProcess->setData('failedPaymentProcedure', $failedPaymentProcedure);

            if ($Payment) {
                $OrderInProcess->setData('orderedWithCostsPayment', $Payment->getId());
            }

            $OrderInProcess->save(QUI::getUsers()->getSystemUser());

            QUI::getSession()->set(
                'termsAndConditions-' . $OrderInProcess->getUUID(),
                1
            );

            if ($Payment && $Payment->isSuccessful($OrderInProcess->getUUID())) {
                $Order = $OrderInProcess->createOrder(QUI::getUsers()->getSystemUser());
                $this->setAttribute('orderHash', $Order->getUUID());
            } else {
                $this->setAttribute('orderHash', $OrderInProcess->getUUID());
            }
        }

        return $this->getOrderProcessStep();
    }

    /**
     * @return mixed[]
     * @throws QUI\ERP\Order\Basket\Exception
     * @throws \Exception
     */
    public function getOrderProcessStep(): array
    {
        $Order = $this->getProcessOrder();

        $processingStep = new QUI\ERP\Order\Controls\OrderProcess\Processing([
            'Order' => $Order,
            'priority' => 40
        ]);

        // init order process
        $OrderProcess = new QUI\ERP\Order\OrderProcess([
            'Order' => $Order,
            'orderHash' => $Order->getUUID(),
            'step' => 'Processing',
            'events' => [
                'onQuiqqerOrderProcessStepsEnd' => function (
                    QUI\ERP\Order\OrderProcess $instance,
                    AbstractOrder $Order,
                    OrderProcessSteps $Steps
                ) use ($processingStep) {
                    if ($Order->getPayment() && !$Order->getPayment()->isSuccessful($Order->getUUID())) {
                        $Steps->clear();
                        $Steps->append($processingStep);
                    }
                }
            ]
        ]);

        $result = $OrderProcess->create();
        $current = $OrderProcess->getCurrentStep()->getName();

        return [
            'html' => $result,
            'step' => $current,
            'url' => $OrderProcess->getStepUrl($current),
            'hash' => $OrderProcess->getStepHash(),
            'orderHash' => $Order->getUUID(),
            'productCount' => $Order->getArticles()->count(),
        ];
    }

    /**
     * Resolve the current process order from the active hash.
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\Database\Exception
     */
    public function getProcessOrder(): AbstractOrder
    {
        $OrderHandler = QUI\ERP\Order\Handler::getInstance();
        $orderHash = $this->getAttribute('orderHash');

        if (!$orderHash) {
            throw new QUI\ERP\Order\Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.not.found'),
                QUI\ERP\Order\Handler::ERROR_ORDER_NOT_FOUND
            );
        }

        try {
            return $OrderHandler->getOrderByHash($orderHash);
        } catch (QUI\Exception) {
            return $OrderHandler->getOrderInProcessByHash($orderHash);
        }
    }

    // region getter

    /**
     * @throws Exception
     * @throws QUI\ERP\Order\Exception
     */
    public function getOrder(): ?OrderInProcess
    {
        $Order = null;
        $Orders = QUI\ERP\Order\Handler::getInstance();

        if ($this->getAttribute('orderHash')) {
            try {
                return $Orders->getOrderInProcessByHash($this->getAttribute('orderHash'));
            } catch (QUI\Exception) {
            }
        }

        try {
            $result = QUI::getEvents()->fireEvent('orderProcessGetOrder', [$this]);

            if (!empty($result)) {
                $OrderInstance = null;

                foreach ($result as $entry) {
                    if ($entry && in_array(OrderInterface::class, class_implements($entry))) {
                        $OrderInstance = $entry;
                    }
                }

                if ($OrderInstance && in_array(OrderInterface::class, class_implements($OrderInstance))) {
                    return $OrderInstance;
                }
            }
        } catch (\Exception) {
        }

        try {
            // select the last order in processing
            $OrderInProcess = $Orders->getLastOrderInProcessFromUser($this->getUser());

            if (!$OrderInProcess->getOrderId()) {
                $Order = $OrderInProcess;
            }
        } catch (QUI\Exception) {
        }

        if ($Order === null) {
            // if no order exists, we create one
            $Order = QUI\ERP\Order\Factory::getInstance()->createOrderInProcess();
        }

        return $Order;
    }

    /**
     * Returns the user associated with the current session.
     *
     * @return QUI\Interfaces\Users\User The user object.
     */
    public function getUser(): QUI\Interfaces\Users\User
    {
        return QUI::getUserBySession();
    }

    /**
     * Get the shipping HTML for the current order.
     *
     * @return string The shipping information
     * @throws \Exception
     */
    public function getDelivery(): string
    {
        $Delivery = new CheckoutDelivery($this);
        $Output = new QUI\Output();
        $result = $Delivery->create();
        $css = QUI\Control\Manager::getCSS();

        try {
            return $Output->parse($css . $result);
        } catch (QUI\Exception $exception) {
            QUI\System\Log::writeException($exception);
            return '';
        }
    }

    /**
     * Get the shipping HTML for the current order.
     *
     * @return string The shipping information
     * @throws \Exception
     */
    public function getShipping(): string
    {
        $Shipping = new CheckoutShipping($this);
        $Output = new QUI\Output();
        $result = $Shipping->create();
        $css = QUI\Control\Manager::getCSS();

        try {
            return $Output->parse($css . $result);
        } catch (QUI\Exception $exception) {
            QUI\System\Log::writeException($exception);
            return '';
        }
    }

    /**
     * Get the payment HTML for the current order.
     *
     * @return string The shipping information
     * @throws \Exception
     */
    public function getPayments(): string
    {
        $Payments = new CheckoutPayment($this);
        $Output = new QUI\Output();
        $result = $Payments->create();
        $css = QUI\Control\Manager::getCSS();

        try {
            return $Output->parse($css . $result);
        } catch (QUI\Exception $exception) {
            QUI\System\Log::writeException($exception);
            return '';
        }
    }

    /**
     * @throws ExceptionBasketNotFound
     * @throws QUI\ERP\Order\Basket\Exception
     * @throws QUI\Database\Exception
     * @throws \Exception
     */
    public function getBasket(): string
    {
        $Basket = new Basket($this);
        $Output = new QUI\Output();
        $result = $Basket->create();
        $css = QUI\Control\Manager::getCSS();

        try {
            return $Output->parse($css . $result);
        } catch (QUI\Exception $exception) {
            QUI\System\Log::writeException($exception);
            return '';
        }
    }

    //endregion
}
