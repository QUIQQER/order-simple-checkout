<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;

use function dirname;

class CheckoutPayment extends QUI\Control
{
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/CheckoutPayment.css');
        $this->addCSSClass('quiqqer-simple-checkout-payment');
    }

    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        return $Engine->fetch(dirname(__FILE__) . '/CheckoutPayment.html');
    }
}
