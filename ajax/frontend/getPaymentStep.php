<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_getPaymentStep
 */

use QUI\ERP\Order\SimpleCheckout\Checkout;

QUI::$Ajax->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_getPaymentStep',
    function ($orderHash) {
        $Checkout = new Checkout([
            'orderHash' => $orderHash
        ]);

        return $Checkout->getOrderProcessStep();
    },
    ['orderHash']
);
