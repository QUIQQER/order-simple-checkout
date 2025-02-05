<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_basket
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_basket',
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

        $Checkout = new QUI\ERP\Order\SimpleCheckout\Checkout([
            'orderHash' => $orderHash,
            'disableAddress' => $settings['disableAddress'],
            'disableProductLinks' => $settings['disableProductLinks']
        ]);

        return $Checkout->getBody();
    },
    ['orderHash', 'settings']
);
