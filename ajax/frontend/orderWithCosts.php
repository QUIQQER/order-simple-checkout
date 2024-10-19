<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_orderWithCosts
 */

use QUI\ERP\Order\SimpleCheckout\Checkout;

QUI::getAjax()->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_orderWithCosts',
    function ($orderHash) {
        $SessionUser = QUI::getUserBySession();
        $userIsGuest = false;

        if ($SessionUser->getId() === 6) {
            $userIsGuest = true;
        }

        $Checkout = new Checkout([
            'orderHash' => $orderHash
        ]);

        $Order = $Checkout->getOrder();

        if (!$Order) {
            throw new QUI\Exception('Checkout has no order');
        }

        // no products
        if (!$Order->getArticles()->count()) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/order-simple-checkout', 'exception.order.has.no.items')
            );
        }

        $InvoiceAddress = $Order->getInvoiceAddress();
        $DefaultAddress = $SessionUser->getStandardAddress();
        $hasDeliveryAddress = $Order->hasDeliveryAddress();

        // check addresses
        if (method_exists($SessionUser, 'getAddressList')) {
            $addresses = $SessionUser->getAddressList();

            foreach ($addresses as $Address) {
                if (Checkout::isSameAddress($InvoiceAddress, $Address)) {
                    $Order->setInvoiceAddress($Address);
                    $Order->save(QUI::getUsers()->getSystemUser());

                    return $Checkout->orderWithCosts();
                }
            }
        }

        // if default address is empty, we set it
        if (
            $DefaultAddress
            && $DefaultAddress->getAttribute('firstname') === ''
            && $DefaultAddress->getAttribute('lastname') === ''
            && $DefaultAddress->getAttribute('street_no') === ''
            && $DefaultAddress->getAttribute('zip') === ''
            && $DefaultAddress->getAttribute('city') === ''
            && method_exists($DefaultAddress, 'save')
            && $hasDeliveryAddress === false
        ) {
            // set invoice address to default, if only invoice exist and no delivery
            $DefaultAddress->setAttribute('firstname', $InvoiceAddress->getAttribute('firstname'));
            $DefaultAddress->setAttribute('lastname', $InvoiceAddress->getAttribute('lastname'));
            $DefaultAddress->setAttribute('street_no', $InvoiceAddress->getAttribute('street_no'));
            $DefaultAddress->setAttribute('zip', $InvoiceAddress->getAttribute('zip'));
            $DefaultAddress->setAttribute('city', $InvoiceAddress->getAttribute('city'));
            $DefaultAddress->setAttribute('country', $InvoiceAddress->getAttribute('country'));

            if ($InvoiceAddress->getAttribute('company')) {
                $DefaultAddress->setAttribute('company', $InvoiceAddress->getAttribute('company'));
            }

            $DefaultAddress->save(QUI::getUsers()->getSystemUser());
            $Order->setInvoiceAddress($DefaultAddress);
            $Order->save(QUI::getUsers()->getSystemUser());
        } elseif (
            $DefaultAddress
            && $DefaultAddress->getAttribute('firstname') === ''
            && $DefaultAddress->getAttribute('lastname') === ''
            && $DefaultAddress->getAttribute('street_no') === ''
            && $DefaultAddress->getAttribute('zip') === ''
            && $DefaultAddress->getAttribute('city') === ''
            && method_exists($DefaultAddress, 'save')
            && $hasDeliveryAddress
        ) {
            // set delivery address to default
            $DeliveryAddress = $Order->getInvoiceAddress();

            // set invoice address to default
            $DefaultAddress->setAttribute('firstname', $DeliveryAddress->getAttribute('firstname'));
            $DefaultAddress->setAttribute('lastname', $DeliveryAddress->getAttribute('lastname'));
            $DefaultAddress->setAttribute('street_no', $DeliveryAddress->getAttribute('street_no'));
            $DefaultAddress->setAttribute('zip', $DeliveryAddress->getAttribute('zip'));
            $DefaultAddress->setAttribute('city', $DeliveryAddress->getAttribute('city'));
            $DefaultAddress->setAttribute('country', $DeliveryAddress->getAttribute('country'));

            if ($InvoiceAddress->getAttribute('company')) {
                $DefaultAddress->setAttribute('company', $InvoiceAddress->getAttribute('company'));
            }

            $DefaultAddress->save(QUI::getUsers()->getSystemUser());
        } elseif (method_exists($SessionUser, 'addAddress') && !$userIsGuest) {
            // add new address
            $NewAddress = $SessionUser->addAddress($InvoiceAddress->getAttributes());

            if ($NewAddress) {
                $Order->setInvoiceAddress($NewAddress);
            }
        }

        $Order->save(QUI::getUsers()->getSystemUser());

        return $Checkout->orderWithCosts();
    },
    ['orderHash']
);
