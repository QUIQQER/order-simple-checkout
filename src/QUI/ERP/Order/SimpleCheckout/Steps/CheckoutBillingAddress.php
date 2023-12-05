<?php

namespace QUI\ERP\Order\SimpleCheckout\Steps;

use QUI;
use QUI\ERP\Order\SimpleCheckout\Checkout;
use QUI\ERP\Order\SimpleCheckout\CheckoutStepInterface;
use QUI\Exception;

use function dirname;

/**
 * Class CheckoutDelivery
 *
 * @package Your\Package\Namespace
 */
class CheckoutBillingAddress extends QUI\Control implements CheckoutStepInterface
{
    protected Checkout $Checkout;

    /**
     * Constructor method for the SimpleCheckoutDelivery class.
     *
     * @param Checkout $Checkout
     * @param array $attributes
     * @return void
     */
    public function __construct(Checkout $Checkout, $attributes = [])
    {
        $this->Checkout = $Checkout;

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/CheckoutBillingAddress.css');
        $this->addCSSClass('quiqqer-simple-checkout-billing quiqqer-simple-checkout-step');

        $this->setJavaScriptControl(
            'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutBillingAddress'
        );
    }

    /**
     * Returns the HTML body content for the checkout delivery step.
     *
     * @return string The HTML body content for the checkout delivery step.
     * @throws Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $User = QUI::getUserBySession();

        $Engine->assign([
            'User' => $User,
            'Address' => $this->getDeliveryAddress()
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/CheckoutBillingAddress.html');
    }

    /**
     * Retrieves the invoice address for the current user.
     *
     * @return null|QUI\ERP\Address
     */
    protected function getDeliveryAddress(): ?QUI\ERP\Address
    {
        return $this->Checkout->getOrder()->getDeliveryAddress();
    }

    /**
     * Validates the invoice address of the current order.
     *
     * @throws QUI\ERP\Order\Exception|Exception
     */
    public function validate()
    {
        QUI\ERP\Order\Controls\OrderProcess\CustomerData::validateAddress(
            $this->Checkout->getOrder()->getInvoiceAddress()
        );
    }
}
