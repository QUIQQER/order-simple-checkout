<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;

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

        parent::__construct($attributes);
    }

    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $template = dirname(__FILE__) . '/Checkout.html';

        if ($this->getAttribute('template') && file_exists($this->getAttribute('template'))) {
            $template = $this->getAttribute('template');
        }


        return $Engine->assign($template);
    }
}
