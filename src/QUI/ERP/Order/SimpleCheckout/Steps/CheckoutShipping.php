<?php

namespace QUI\ERP\Order\SimpleCheckout\Steps;

use QUI;
use QUI\ERP\Order\SimpleCheckout\Checkout;
use QUI\ERP\Order\SimpleCheckout\CheckoutStepInterface;

use function dirname;

class CheckoutShipping extends QUI\Control implements CheckoutStepInterface
{
    protected Checkout $Checkout;

    public function __construct(Checkout $Checkout, $attributes = [])
    {
        $this->Checkout = $Checkout;

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/CheckoutShipping.css');
        $this->addCSSClass('quiqqer-simple-checkout-shipping quiqqer-simple-checkout-step');
        $this->setJavaScriptControl(
            'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutShipping'
        );
    }

    public function getBody(): string
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
            return '';
        }

        $Engine = QUI::getTemplateManager()->getEngine();
        $Delivery = new CheckoutDelivery($this->Checkout);

        try {
            $Delivery->validate();
        } catch (QUI\Exception) {
            return $Engine->fetch(dirname(__FILE__) . '/CheckoutShipping.html');
        }

        $Shipping = new QUI\ERP\Shipping\Order\Shipping([
            'Order' => $this->Checkout->getOrder()
        ]);

        $Engine->assign('Shipping', $Shipping);

        return $Engine->fetch(dirname(__FILE__) . '/CheckoutShipping.html');
    }
}
