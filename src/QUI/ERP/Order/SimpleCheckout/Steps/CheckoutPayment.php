<?php

namespace QUI\ERP\Order\SimpleCheckout\Steps;

use QUI;
use QUI\ERP\Order\SimpleCheckout\Checkout;
use QUI\ERP\Order\SimpleCheckout\CheckoutStepInterface;
use QUI\ERP\Order\SimpleCheckout\Payments\SimpleCheckoutPayment;

use function dirname;

class CheckoutPayment extends QUI\Control implements CheckoutStepInterface
{
    protected Checkout $Checkout;

    /**
     * @param Checkout $Checkout
     * @param mixed[] $attributes
     */
    public function __construct(Checkout $Checkout, array $attributes = [])
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
        $validateAddress = true;

        if (
            class_exists('QUI\ERP\Order\Guest\GuestOrder') &&
            QUI\ERP\Order\Guest\GuestOrder::isAnonymousOrder()
        ) {
            $validateAddress = false;
        }

        if (class_exists('QUI\ERP\Accounting\Invoice\Utils\Invoice')) {
            $addressRequired = QUI\ERP\Accounting\Invoice\Utils\Invoice::addressRequirement();

            if ($addressRequired === false) {
                $validateAddress = false; // we don't need a address
            }
        }

        if ($validateAddress) {
            try {
                $Delivery->validate();
            } catch (QUI\Exception) {
                return $Engine->fetch(__DIR__ . '/CheckoutPayment.html');
            }
        }

        $Payment = new SimpleCheckoutPayment([
            'Order' => $this->Checkout->getOrder()
        ]);

        $Engine->assign('Payment', $Payment);

        return $Engine->fetch(dirname(__FILE__) . '/CheckoutPayment.html');
    }
}
