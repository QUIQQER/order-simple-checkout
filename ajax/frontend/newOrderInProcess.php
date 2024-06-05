<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_newOrderInProcess
 */

use QUI\ERP\Products\Handler\Products;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_newOrderInProcess',
    function ($products) {
        $products = json_decode($products, true);
        $Orders = QUI\ERP\Order\Handler::getInstance();
        $UserSession = QUI::getUserBySession();

        if (!count($products)) {
            // select the last order in processing
            try {
                return $Orders->getLastOrderInProcessFromUser($UserSession)->getUUID();
            } catch (QUI\Exception) {
                return QUI\ERP\Order\Factory::getInstance()->createOrderInProcess()->getUUID();
            }
        }

        if (
            QUI::getUsers()->isNobodyUser($UserSession)
            && QUI::getPackageManager()->isInstalled('quiqqer/order-guestorder')
            && class_exists('QUI\ERP\Order\Guest\GuestOrder')
            && !QUI\ERP\Order\Guest\GuestOrder::isActive()
        ) {
            throw new QUI\Exception('Please log in');
        }

        try {
            $OrderInProcess = QUI\ERP\Order\Factory::getInstance()->createOrderInProcess();
        } catch (Exception) {
        }

        if (!isset($OrderInProcess)) {
            throw new QUI\Exception('Not allowed');
        }

        foreach ($products as $product) {
            try {
                $productId = null;

                if (isset($product['productId'])) {
                    $productId = $product['productId'];
                }

                if (isset($product['id'])) {
                    $productId = $product['id'];
                }

                if (empty($productId)) {
                    continue;
                }

                Products::getProduct($productId); // check if product exists

                if (empty($product['fields'])) {
                    $product['fields'] = [];
                }

                $BasketProduct = new QUI\ERP\Order\Basket\Product($productId, [
                    'fields' => $product['fields']
                ]);

                if (isset($product['quantity'])) {
                    $BasketProduct->setQuantity($product['quantity']);
                }

                $OrderInProcess->addArticle($BasketProduct->toArticle());
            } catch (Exception) {
                continue;
            }
        }

        $OrderInProcess->save();

        return $OrderInProcess->getUUID();
    },
    ['products']
);
