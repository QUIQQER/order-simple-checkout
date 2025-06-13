<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_getSimpleCheckoutControl
 */

use QUI\ERP\Order\SimpleCheckout\Checkout;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_getSimpleCheckoutControl',
    function ($orderHash, $settings) {
        if (!empty($settings)) {
            $settings = json_decode($settings, true);
        }

        if (!is_array($settings)) {
            $settings = [];
        }

        $Checkout = new Checkout([
            'orderHash' => $orderHash,
            'disableAddress' => $settings['disableAddress'] ?? false,
            'disableProductLinks' => $settings['disableProductLinks'] ?? 'default',
            'showEmail' => $settings['showEmail'] ?? false
        ]);

        $Output = new QUI\Output();
        $result = $Checkout->create();
        $css = QUI\Control\Manager::getCSS();

        return $Output->parse($css . $result);
    },
    ['orderHash', 'settings']
);
