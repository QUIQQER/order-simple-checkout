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

        if (!isset($settings['disableAddress'])) {
            $settings['disableAddress'] = false;
        }

        if (!isset($settings['disableProductLinks'])) {
            $settings['disableProductLinks'] = 'default';
        }

        $Checkout = new Checkout([
            'orderHash' => $orderHash,
            'disableAddress' => $settings['disableAddress'],
            'disableProductLinks' => $settings['disableProductLinks']
        ]);

        $Output = new QUI\Output();
        $result = $Checkout->create();
        $css = QUI\Control\Manager::getCSS();

        return $Output->parse($css . $result);
    },
    ['orderHash', 'settings']
);
