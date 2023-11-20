<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_getOrder
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_getOrder',
    function ($orderHash) {
        $OrderHandler = QUI\ERP\Order\Handler::getInstance();
        $User = QUI::getUserBySession();

        try {
            $Order = $OrderHandler->getOrderByHash($orderHash);

            if (
                QUI\Permissions\Permission::isAdmin($User)
                || QUI\Permissions\Permission::isSU($User)
            ) {
                return $Order->toArray();
            }

            if ($User->getId() === $Order->getCustomer()->getId()) {
                return $Order->toArray();
            }
        } catch (QUI\Exception $exception) {
        }

        return null;
    },
    ['orderHash']
);
