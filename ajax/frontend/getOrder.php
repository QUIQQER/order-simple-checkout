<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_getOrder
 */

use QUI\ERP\Address;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\SimpleCheckout\Checkout;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_getOrder',
    function ($orderHash) {
        $User = QUI::getUserBySession();
        $Address = $User->getStandardAddress();
        $Checkout = new Checkout([
            'orderHash' => $orderHash
        ]);

        $result = [
            'order' => false,
            'address' => $Address?->getAttributes() ?? false
        ];

        try {
            $Order = $Checkout->getProcessOrder();
            $orderHash = $Order->getUUID();
        } catch (QUI\Exception) {
            $Order = $Checkout->getOrder();
            $orderHash = $Order->getUUID();
        }

        if ($Order instanceof OrderInProcess) {
            $Payment = $Order->getPayment();

            if (
                $Payment
                && $Payment->isSuccessful($Order->getUUID())
                && !$Order->getOrderId()
            ) {
                $Order = $Order->createOrder(QUI::getUsers()->getSystemUser());
                $orderHash = $Order->getUUID();
            }
        }

        $Customer = $Order->getCustomer();
        $customerId = $Customer->getUUID();

        if ($Address) {
            $Order->setInvoiceAddress($Address);
            $Order->setDeliveryAddress(new Address($Address->getAttributes(), $User));
            $Order->setData('sc_needs_recalc', 1);
            $Order->save(QUI::getUserBySession());
        }

        if ($User->getUUID() === $customerId) {
            $result['order'] = $Order->toArray();
        }

        return $result;
    },
    ['orderHash']
);
