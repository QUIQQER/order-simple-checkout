<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_getSimpleCheckoutControl
 */

use QUI\ERP\Order\SimpleCheckout\Checkout;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_getSimpleCheckoutControl',
    function ($orderHash) {
        $Checkout = new Checkout([
            'orderHash' => $orderHash
        ]);

        $Output = new QUI\Output();
        $result = $Checkout->create();
        $css = QUI\Control\Manager::getCSS();

        return $Output->parse($css . $result);
    },
    ['orderHash']
);
