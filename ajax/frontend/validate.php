<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_validate
 */

use QUI\ERP\Order\SimpleCheckout\Checkout;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_validate',
    function ($orderHash) {
        $Checkout = new Checkout(['orderHash' => $orderHash]);

        return $Checkout->gatherMissingOrderDetails();
    },
    ['orderHash']
);
