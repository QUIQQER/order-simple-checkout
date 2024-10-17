<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_getOrder
 */

use QUI\ERP\Address;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_getOrder',
    function ($orderHash) {
        $OrderHandler = QUI\ERP\Order\Handler::getInstance();
        $User = QUI::getUserBySession();
        $Address = $User->getStandardAddress();

        $result = [
            'order' => false,
            'address' => $Address->getAttributes()
        ];

        try {
            $Order = $OrderHandler->getOrderByHash($orderHash);
            $Customer = $Order->getCustomer();
            $customerId = $Customer->getUUID();

            $Order->setInvoiceAddress($Address);
            $Order->setDeliveryAddress(new Address($Address->getAttributes(), $User));
            $Order->save(QUI::getUserBySession());


            if ($User->getUUID() === $customerId) {
                $result['order'] = $Order->toArray();
            }
        } catch (QUI\Exception) {
        }

        return $result;
    },
    ['orderHash']
);
