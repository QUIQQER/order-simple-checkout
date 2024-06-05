<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_getOrder
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_getOrder',
    function ($orderHash) {
        $OrderHandler = QUI\ERP\Order\Handler::getInstance();
        $User = QUI::getUserBySession();

        try {
            $Order = $OrderHandler->getOrderByHash($orderHash);
            $Customer = $Order->getCustomer();
            $customerId = $Customer->getUUID();

            if ($User->getUUID() === $customerId) {
                return $Order->toArray();
            }

            return false;
        } catch (QUI\Exception) {
        }

        return null;
    },
    ['orderHash']
);
