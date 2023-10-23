<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_setCurrency
 */

use QUI\ERP\Order\SimpleCheckout\Checkout;

QUI::$Ajax->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_setCurrency',
    function ($orderHash, $currency) {
        $Checkout = new Checkout(['orderHash' => $orderHash]);
        $Order = $Checkout->getOrder();
        $Currency = QUI\ERP\Currency\Handler::getCurrency($currency);

        $Order->setCurrency($Currency);
        $Order->save();
    },
    ['orderHash', 'currency']
);
