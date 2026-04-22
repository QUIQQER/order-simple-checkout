<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_setPayment
 */

use QUI\ERP\Order\SimpleCheckout\Checkout;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_setPayment',
    function ($orderHash, $paymentId) {
        $Checkout = new Checkout([
            'orderHash' => $orderHash
        ]);

        $Order = $Checkout->getOrder();

        if (!$Order) {
            throw new QUI\Exception('Checkout has no order in process');
        }

        $currentPaymentId = $Order->getPayment()?->getId();
        $newPaymentId = (int)$paymentId;

        if ($currentPaymentId === $newPaymentId) {
            return true;
        }

        if ($newPaymentId <= 0) {
            $Order->clearPayment();
        } else {
            $Order->setPayment($newPaymentId);
        }

        $Order->setData('sc_needs_recalc', 1);
        $Order->save();

        return true;
    },
    ['orderHash', 'paymentId']
);
