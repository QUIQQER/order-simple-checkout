<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\SimpleCheckout\Steps\CheckoutDelivery;
use QUI\ERP\Order\SimpleCheckout\Steps\CheckoutPayment;
use QUI\ERP\Order\SimpleCheckout\Steps\CheckoutShipping;
use QUI\Exception;

use function dirname;
use function file_exists;

/**
 * Class Checkout
 *
 * Represents the simple checkout control
 */
class Checkout extends QUI\Control
{
    public function __construct($attributes = [])
    {
        $this->setAttributes([
            'orderHash' => false,
            'template' => false,
            'data-qui' => 'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout'
        ]);

        $this->addCSSClass('quiqqer-simple-checkout');
        $this->addCSSFile(dirname(__FILE__) . '/Checkout.css');

        parent::__construct($attributes);
    }

    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $template = dirname(__FILE__) . '/Checkout.html';

        if ($this->getAttribute('template') && file_exists($this->getAttribute('template'))) {
            $template = $this->getAttribute('template');
        }

        // put the basket articles to the order in process
        $Basket = QUI\ERP\Order\Handler::getInstance()->getBasketFromUser($this->getUser());
        $Basket->toOrder($this->getOrder());

        $Checkout = new QUI\ERP\Order\Controls\OrderProcess\Checkout();

        // terms and conditions
        $termsAndConditions = QUI::getLocale()->get(
            'quiqqer/order',
            'ordering.step.checkout.checkoutAcceptText',
            [
                'terms_and_conditions' => $Checkout->getLinkOf('terms_and_conditions')
            ]
        );

        QUI::getEvents()->fireEvent(
            'quiqqerOrderSimpleCheckoutOutput',
            [$this, &$termsAndConditions]
        );


        $Engine->assign([
            'Order' => $this->getOrder(),
            'Basket' => $Basket,
            'BasketDisplay' => new Basket($this),
            'User' => $this->getUser(),
            'Delivery' => new CheckoutDelivery($this),
            'Shipping' => new CheckoutShipping($this),
            'Payment' => new CheckoutPayment($this),
            'termsAndConditions' => $termsAndConditions
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
        // check address
        try {
            $Order = $this->getOrder();

            QUI\ERP\Order\Controls\OrderProcess\CustomerData::validateAddress(
                $Order->getInvoiceAddress()
            );
        } catch (QUI\Exception $exception) {
            return false;
        }

        // check payment
        $Payment = $Order->getPayment();

        if (!$Payment) {
            return false;
        }

        // check shipping
        if (QUI::getPackageManager()->isInstalled('quiqqer/shipping') && !$Order->getShipping()) {
            return false;
        }

        return true;
    }

    public function orderWithCosts(): array
    {
        $OrderProcess = $this->getOrder();
        $Order = $OrderProcess->createOrder(QUI::getUsers()->getSystemUser());
        $Order->setData('orderedWithCosts', true);
        $Order->save(QUI::getUsers()->getSystemUser());

        // init order process
        $OrderProcess = new QUI\ERP\Order\OrderProcess([
            'orderHash' => $Order->getHash(),
            'step' => 'Processing'
        ]);

        $result = $OrderProcess->create();
        $current = false;

        if ($OrderProcess->getCurrentStep()) {
            $current = $OrderProcess->getCurrentStep()->getName();
        }

        return [
            'html' => $result,
            'step' => $current,
            'url' => $OrderProcess->getStepUrl($current),
            'hash' => $OrderProcess->getStepHash()
        ];
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
            } catch (QUI\Exception $exception) {
            }
        }

        try {
            // select the last order in processing
            $OrderInProcess = $Orders->getLastOrderInProcessFromUser($this->getUser());

            if (!$OrderInProcess->getOrderId()) {
                $Order = $OrderInProcess;
            }
        } catch (QUI\Exception $Exception) {
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
     * Get the shipping html for the current order.
     *
     * @return string The shipping information
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
     * Get the payment html for the current order.
     *
     * @return string The shipping information
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
