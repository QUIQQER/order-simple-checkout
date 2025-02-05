<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_orderWithCosts
 */

use QUI\ERP\Order\SimpleCheckout\Checkout;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_orderWithCosts',
    function ($orderHash) {
        $Checkout = new Checkout([
            'orderHash' => $orderHash
        ]);

        $Order = $Checkout->getOrder();

        if (!$Order) {
            throw new QUI\Exception('Checkout has no order');
        }

        // no products
        if (!$Order->getArticles()->count()) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/order-simple-checkout', 'exception.order.has.no.items')
            );
        }

        if (!$Checkout->isValid()) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/order-simple-checkout', 'exception.order.has.no.items')
            );
        }

        return $Checkout->orderWithCosts();
    },
    ['orderHash']
);
