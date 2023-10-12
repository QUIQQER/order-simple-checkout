<?php

namespace QUI\ERP\Order\SimpleCheckout\Steps;

use QUI;
use QUI\ERP\Accounting\Payments\Order\Payment;
use QUI\ERP\Order\SimpleCheckout\Checkout;
use QUI\ERP\Order\SimpleCheckout\CheckoutStepInterface;

use function dirname;

class CheckoutPayment extends QUI\Control implements CheckoutStepInterface
{
    protected Checkout $Checkout;

    public function __construct(Checkout $Checkout, $attributes = [])
    {
        $this->Checkout = $Checkout;

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/CheckoutPayment.css');
        $this->addCSSClass('quiqqer-simple-checkout-payment quiqqer-simple-checkout-step');
        $this->setJavaScriptControl(
            'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutPayment'
        );
    }

    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Delivery = new CheckoutDelivery($this->Checkout);

        try {
            $Delivery->validate();
        } catch (QUI\Exception $exception) {
            return $Engine->fetch(dirname(__FILE__) . '/CheckoutPayment.html');
        }


        $Payment = new Payment([
            'Order' => $this->Checkout->getOrder()
        ]);

        $Engine->assign('Payment', $Payment);

        return $Engine->fetch(dirname(__FILE__) . '/CheckoutPayment.html');
    }
}
