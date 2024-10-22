<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_delivery
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_delivery',
    function ($orderHash, $addressId) {
        $Checkout = new QUI\ERP\Order\SimpleCheckout\Checkout([
            'orderHash' => $orderHash
        ]);

        if (!empty($addressId)) {
            try {
                $Order = $Checkout->getOrder();
                $Address = QUI::getUserBySession()->getAddress($addressId);

                if ($Order) {
                    $ErpAddress = new QUI\ERP\Address($Address->getAttributes(), QUI::getUserBySession());
                    $Order->setDeliveryAddress($ErpAddress);
                    $Order->setInvoiceAddress($ErpAddress);
                    $Order->save();
                }
            } catch (QUI\Exception) {
            }
        }

        return $Checkout->getDelivery();
    },
    ['orderHash', 'addressId']
);
