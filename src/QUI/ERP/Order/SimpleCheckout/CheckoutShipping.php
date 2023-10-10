<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;

use function dirname;

class CheckoutShipping extends QUI\Control
{
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/CheckoutShipping.css');
        $this->addCSSClass('quiqqer-simple-checkout-shipping');
    }

    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        return $Engine->fetch(dirname(__FILE__) . '/CheckoutShipping.html');
    }
}
