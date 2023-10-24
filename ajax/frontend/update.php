<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_setAddress
 */

use QUI\ERP\Address;
use QUI\ERP\Order\SimpleCheckout\Checkout;
use QUI\ERP\Shipping\Shipping;

QUI::$Ajax->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_update',
    function ($orderHash, $orderData) {
        $orderData = json_decode($orderData, true);

        if (!is_array($orderData)) {
            return false;
        }

        if (isset($orderData['street']) && isset($orderData['street_number'])) {
            $orderData['street_no'] = $orderData['street'] . ' ' . $orderData['street_number'];
        }

        $Checkout = new Checkout(['orderHash' => $orderHash]);
        $Order = $Checkout->getOrder();
        $Order->setInvoiceAddress(new Address($orderData));

        if (!empty($orderData['shipping']) && QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
            $Order->setShipping(
                Shipping::getInstance()->getShippingEntry($orderData['shipping'])
            );
        } else {
            $Order->removeShipping();
        }

        if (!empty($orderData['payment'])) {
            $Order->setPayment($orderData['payment']);
        } else {
            $Order->clearPayment();
        }

        $Order->save();

        return $Checkout->isValid();
    },
    ['orderHash', 'orderData']
);
