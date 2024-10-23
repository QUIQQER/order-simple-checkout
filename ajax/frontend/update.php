<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_setAddress
 */

use QUI\ERP\Address;
use QUI\ERP\Order\SimpleCheckout\Checkout;
use QUI\ERP\Shipping\Shipping;
use QUI\System\Log;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_update',
    function ($orderHash, $orderData) {
        $orderData = json_decode($orderData, true);
        $User = QUI::getUserBySession();

        if (!is_array($orderData)) {
            return false;
        }

        if (isset($orderData['street']) && isset($orderData['street_number'])) {
            $orderData['street_no'] = $orderData['street'] . ' ' . $orderData['street_number'];
        }

        $Checkout = new Checkout(['orderHash' => $orderHash]);
        $Order = $Checkout->getOrder();

        $erpAddressData = [
            'salutation' => $orderData['salutation'],
            'firstname' => $orderData['firstname'],
            'lastname' => $orderData['lastname'],
            'street_no' => $orderData['street_no'],
            'zip' => $orderData['zip'],
            'city' => $orderData['city'],
            'country' => $orderData['country']
        ];

        if (isset($orderData['company'])) {
            $erpAddressData['company'] = $orderData['company'];
        }

        // get user address
        if (isset($orderData['addresses'])) {
            try {
                $Address = $User->getAddress($orderData['addresses']);
            } catch (QUI\Exception) {
                $Address = $User->getStandardAddress();
            }
        } elseif (isset($orderData['address'])) {
            try {
                $Address = $User->getAddress($orderData['address']);
            } catch (QUI\Exception) {
                $Address = $User->getStandardAddress();
            }
        } else {
            $Address = $User->getStandardAddress();
        }

        $erpAddressData['uuid'] = $Address->getUUID();
        $erpAddressData['id'] = $Address->getId();
        $ErpAddress = new Address($erpAddressData);
        $address = $ErpAddress->getAttributes();

        foreach ($address as $k => $v) {
            $Address->setAttribute($k, $v);
        }

        $Address->save(QUI::getUsers()->getSystemUser());

        if (isset($orderData['billing_address']) && $orderData['billing_address'] === 'different') {
            $Order?->setDeliveryAddress($ErpAddress);

            // invoice address / billing address
            if (isset($orderData['billing_street']) && isset($orderData['billing_street_number'])) {
                $orderData['billing_street_no'] = $orderData['billing_street'] . ' ' . $orderData['billing_street_number'];
            }

            $billingAddress = [
                'firstname' => $orderData['billing_firstname'],
                'lastname' => $orderData['billing_lastname'],
                'street_no' => $orderData['billing_street_no'],
                'zip' => $orderData['billing_zip'],
                'city' => $orderData['billing_city'],
                'country' => $orderData['billing_country'],
                'uuid' => $Address->getUUID()
            ];

            if (!empty($orderData['billing_company'])) {
                $billingAddress['company'] = $orderData['billing_company'];
            }

            $Order?->setInvoiceAddress(new Address($billingAddress));
        } else {
            $Order?->setInvoiceAddress($ErpAddress);
            $Order?->removeDeliveryAddress();
        }

        if (!empty($orderData['shipping']) && QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
            $Order?->setShipping(
                Shipping::getInstance()->getShippingEntry($orderData['shipping'])
            );
        } else {
            $Order?->removeShipping();
        }

        if (isset($orderData['businessType'])) {
            try {
                $Customer = $Order->getCustomer();
                $User = QUI::getUsers()->get($Customer->getUUID());

                if ($orderData['businessType'] === 'b2b') {
                    $User->setAttribute('quiqqer.erp.isNettoUser', QUI\ERP\Utils\User::IS_NETTO_USER);
                    $User->setCompanyStatus(true);
                } else {
                    $User->setAttribute('quiqqer.erp.isNettoUser', QUI\ERP\Utils\User::IS_BRUTTO_USER);
                    $User->setCompanyStatus(false);
                }

                if (isset($orderData['vatId'])) {
                    $User->setAttribute('quiqqer.erp.euVatId', $orderData['vatId']);
                }

                if (isset($orderData['chUID'])) {
                    $User->setAttribute('quiqqer.erp.chUID', $orderData['chUID']);
                }

                $User->save(QUI::getUsers()->getSystemUser());
            } catch (QUI\Exception $Exception) {
                Log::addError($Exception->getMessage());
            }
        }

        if (!empty($orderData['payment'])) {
            $Order?->setPayment($orderData['payment']);
        } else {
            $Order?->clearPayment();
        }

        $Order?->save();

        return $Checkout->isValid();
    },
    ['orderHash', 'orderData']
);
