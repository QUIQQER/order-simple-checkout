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

        $ErpAddress = new Address([
            'salutation' => $orderData['salutation'],
            'firstname' => $orderData['firstname'],
            'lastname' => $orderData['lastname'],
            'street_no' => $orderData['street_no'],
            'zip' => $orderData['zip'],
            'city' => $orderData['city'],
            'country' => $orderData['country']
        ]);

        if (isset($orderData['billing_address']) && $orderData['billing_address'] === 'different') {
            $Order->setDeliveryAddress($ErpAddress);

            // invoice address / billing address
            if (isset($orderData['billing_street']) && isset($orderData['billing_street_number'])) {
                $orderData['billing_street_no'] = $orderData['billing_street'] . ' ' . $orderData['billing_street_number'];
            }

            $Order->setInvoiceAddress(
                new Address([
                    'firstname' => $orderData['billing_firstname'],
                    'lastname' => $orderData['billing_lastname'],
                    'street_no' => $orderData['billing_street_no'],
                    'zip' => $orderData['billing_zip'],
                    'city' => $orderData['billing_city'],
                    'country' => $orderData['billing_country']
                ])
            );
        } else {
            $Order->setInvoiceAddress($ErpAddress);
            $Order->removeDeliveryAddress();
        }

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
