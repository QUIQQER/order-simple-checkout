<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;
use QUI\ERP\Order\OrderInProcess;
use QUI\Exception;

use function dirname;
use function file_exists;

class Checkout extends QUI\Control
{
    public function __construct($attributes = [])
    {
        $this->setAttributes([
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

        $Control = new QUI\ERP\Order\Controls\Basket\Small();
        $Control->setBasket(
            QUI\ERP\Order\Handler::getInstance()->getBasketFromUser($this->getUser())
        );

        $Engine->assign([
            'Basket' => $Control,
            'User' => $this->getUser(),
            'Delivery' => new CheckoutDelivery(),
            'Shipping' => new CheckoutShipping(),
            'Payment' => new CheckoutPayment()
        ]);

        return $Engine->fetch($template);
    }

    public function send()
    {
        $Order = $this->getOrder();

        // set all stuff to the order
        $Order->setInvoiceAddress();
        $Order->setShipping();
        $Order->setPayment();


        // all runs fine
        if ($Order instanceof OrderInProcess) {
            $OrderInProcess = $Order;
            $Order = $Order->createOrder();
            $OrderInProcess->delete();
        }
    }

    /**
     * @throws Exception
     * @throws QUI\ERP\Order\Exception
     */
    public function getOrder(): ?OrderInProcess
    {
        $Order = null;
        $Orders = QUI\ERP\Order\Handler::getInstance();

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

    public function getUser(): QUI\Interfaces\Users\User
    {
        return QUI::getUserBySession();
    }
}
