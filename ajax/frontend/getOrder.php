<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_getOrder
 */

use QUI\ERP\Address;
use QUI\ERP\Order\SimpleCheckout\Checkout;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_getOrder',
    function ($orderHash) {
        $OrderHandler = QUI\ERP\Order\Handler::getInstance();
        $User = QUI::getUserBySession();
        $Address = $User->getStandardAddress();

        $result = [
            'order' => false,
            'address' => $Address?->getAttributes() ?? false
        ];

        try {
            $Order = $OrderHandler->getOrderByHash($orderHash);
        } catch (QUI\Exception) {
            $Checkout = new Checkout();
            $Order = $Checkout->getOrder();
            $orderHash = $Order->getUUID();
        }

        $Customer = $Order->getCustomer();
        $customerId = $Customer->getUUID();

        if ($Address) {
            $Order->setInvoiceAddress($Address);
            $Order->setDeliveryAddress(new Address($Address->getAttributes(), $User));
            $Order->save(QUI::getUserBySession());
        }

        if ($User->getUUID() === $customerId) {
            $result['order'] = $OrderHandler->getOrderByHash($orderHash)->toArray();
        }

        return $result;
    },
    ['orderHash']
);
