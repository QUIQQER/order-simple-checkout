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
        $orderDirty = false;
        $userDirty = false;

        $erpAddressData = [
            'salutation' => $orderData['salutation'] ?? '',
            'firstname' => $orderData['firstname'] ?? '',
            'lastname' => $orderData['lastname'] ?? '',
            'street_no' => $orderData['street_no'] ?? '',
            'zip' => $orderData['zip'] ?? '',
            'city' => $orderData['city'] ?? '',
            'country' => $orderData['country'] ?? ''
        ];

        if (isset($orderData['company'])) {
            $erpAddressData['company'] = $orderData['company'];
        }

        // get user address
        // @todo nobody beachten
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

        $addressDirty = false;

        foreach ($address as $k => $v) {
            if ($Address->getAttribute($k) !== $v) {
                $Address->setAttribute($k, $v);
                $addressDirty = true;
            }
        }

        if ($addressDirty) {
            $Address->save(QUI::getUsers()->getSystemUser());
            $orderDirty = true;
        }

        $addressKeys = [
            'id',
            'salutation',
            'firstname',
            'lastname',
            'street_no',
            'zip',
            'city',
            'country',
            'company'
        ];

        $addressesEqual = static function (?Address $Current, array $Data) use ($addressKeys): bool {
            if (!$Current) {
                return false;
            }

            $compare = array_intersect_key($Data, array_flip($addressKeys));

            foreach ($compare as $key => $value) {
                if ($Current->getAttribute($key) !== $value) {
                    return false;
                }
            }

            return true;
        };

        if (isset($orderData['billing_address']) && $orderData['billing_address'] === 'different') {
            if ($Order && !$addressesEqual($Order->getDeliveryAddress(), $ErpAddress->getAttributes())) {
                $Order->setDeliveryAddress($ErpAddress);
                $orderDirty = true;
            }

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

            if ($Order && !$addressesEqual($Order->getInvoiceAddress(), $billingAddress)) {
                $Order->setInvoiceAddress(new Address($billingAddress));
                $orderDirty = true;
            }
        } else {
            if ($Order && !$addressesEqual($Order->getInvoiceAddress(), $ErpAddress->getAttributes())) {
                $Order->setInvoiceAddress($ErpAddress);
                $orderDirty = true;
            }

            if ($Order && $Order->hasDeliveryAddress()) {
                $Order->removeDeliveryAddress();
                $orderDirty = true;
            }
        }

        if (!empty($orderData['shipping']) && QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
            if ($Order) {
                $currentShippingId = $Order->getShipping()?->getId(); // @phpstan-ignore-line
                $newShippingId = (int)$orderData['shipping'];

                if ($currentShippingId !== $newShippingId) {
                    $Order->setShipping(
                        Shipping::getInstance()->getShippingEntry($orderData['shipping'])
                    );
                    $orderDirty = true;
                }
            }
        } else {
            if ($Order && $Order->getShipping()) {
                $Order->removeShipping();
                $orderDirty = true;
            }
        }

        if (isset($orderData['businessType'])) {
            try {
                $Customer = $Order->getCustomer();
                $User = QUI::getUsers()->get($Customer->getUUID());

                if ($orderData['businessType'] === 'b2b') {
                    if ($User->getAttribute('quiqqer.erp.isNettoUser') !== QUI\ERP\Utils\User::IS_NETTO_USER) {
                        $User->setAttribute('quiqqer.erp.isNettoUser', QUI\ERP\Utils\User::IS_NETTO_USER);
                        $userDirty = true;
                    }

                    if ($User->isCompany() === false) {
                        $User->setCompanyStatus(true);
                        $userDirty = true;
                    }
                } else {
                    if ($User->getAttribute('quiqqer.erp.isNettoUser') !== QUI\ERP\Utils\User::IS_BRUTTO_USER) {
                        $User->setAttribute('quiqqer.erp.isNettoUser', QUI\ERP\Utils\User::IS_BRUTTO_USER);
                        $userDirty = true;
                    }

                    if ($User->isCompany() === true) {
                        $User->setCompanyStatus(false);
                        $userDirty = true;
                    }
                }

                if (isset($orderData['vatId'])) {
                    if ($User->getAttribute('quiqqer.erp.euVatId') !== $orderData['vatId']) {
                        $User->setAttribute('quiqqer.erp.euVatId', $orderData['vatId']);
                        $userDirty = true;
                    }
                }

                if (isset($orderData['chUID'])) {
                    if ($User->getAttribute('quiqqer.erp.chUID') !== $orderData['chUID']) {
                        $User->setAttribute('quiqqer.erp.chUID', $orderData['chUID']);
                        $userDirty = true;
                    }
                }

                if ($userDirty) {
                    $User->save(QUI::getUsers()->getSystemUser());
                }

                if ($Order && $userDirty) {
                    $Order->setCustomer($User);
                    $orderDirty = true;
                }
            } catch (QUI\Exception $Exception) {
                Log::addError($Exception->getMessage());
            }
        }

        if (!empty($orderData['payment'])) {
            if ($Order) {
                $currentPaymentId = $Order->getPayment()?->getId();
                $newPaymentId = (int)$orderData['payment'];

                if ($currentPaymentId !== $newPaymentId) {
                    $Order->setPayment($orderData['payment']);
                    $orderDirty = true;
                }
            }
        } else {
            if ($Order && $Order->getPayment()) {
                $Order->clearPayment();
                $orderDirty = true;
            }
        }

        if ($Order && ($orderDirty || $userDirty || $addressDirty)) {
            $Order->setData('sc_needs_recalc', 1);
            $Order->save();
        }

        return $Checkout->isValid();
    },
    ['orderHash', 'orderData']
);
