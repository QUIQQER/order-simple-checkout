<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_shipping
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_shipping',
    function ($orderHash) {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
            return '';
        }

        $Checkout = new QUI\ERP\Order\SimpleCheckout\Checkout([
            'orderHash' => $orderHash
        ]);
        
        return $Checkout->getShipping();
    },
    ['orderHash']
);
