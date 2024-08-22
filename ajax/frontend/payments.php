<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_payments
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_payments',
    function ($orderHash) {
        $Checkout = new QUI\ERP\Order\SimpleCheckout\Checkout([
            'orderHash' => $orderHash
        ]);

        return $Checkout->getPayments();
    },
    ['orderHash']
);
