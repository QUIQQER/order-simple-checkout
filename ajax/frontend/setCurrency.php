<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_setCurrency
 */

use QUI\ERP\Order\SimpleCheckout\Checkout;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_setCurrency',
    function ($orderHash, $currency) {
        if (!QUI::getUserBySession()->getId()) {
            return;
        }

        $Checkout = new Checkout(['orderHash' => $orderHash]);
        $Order = $Checkout->getOrder();
        $Currency = QUI\ERP\Currency\Handler::getCurrency($currency);

        $Order?->setCurrency($Currency);
        $Order?->setData('sc_needs_recalc', 1);
        $Order?->save();
    },
    ['orderHash', 'currency']
);
