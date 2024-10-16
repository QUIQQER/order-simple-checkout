<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_basket
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_basket',
    function ($orderHash) {
        $Checkout = new QUI\ERP\Order\SimpleCheckout\Checkout([
            'orderHash' => $orderHash
        ]);

        return $Checkout->getBody();
    },
    ['orderHash']
);
