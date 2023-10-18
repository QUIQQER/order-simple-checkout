<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_orderWithCosts
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_orderWithCosts',
    function ($orderHash) {
        $Checkout = new QUI\ERP\Order\SimpleCheckout\Checkout([
            'orderHash' => $orderHash
        ]);

        return $Checkout->orderWithCosts();
    },
    ['orderHash']
);
