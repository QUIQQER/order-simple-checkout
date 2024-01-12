<?php

/**
 * This file contains package_quiqqer_order-simple-checkout_ajax_frontend_orderWithCosts
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_order-simple-checkout_ajax_frontend_orderWithCosts',
    function ($orderHash) {
        $SessionUser = QUI::getUserBySession();
        $userIsGuest = false;

        if ($SessionUser->getId() === 6) {
            $userIsGuest = true;
        }

        $Checkout = new QUI\ERP\Order\SimpleCheckout\Checkout([
            'orderHash' => $orderHash
        ]);

        $Order = $Checkout->getOrder();
        $InvoiceAddress = $Order->getInvoiceAddress();
        $DefaultAddress = $SessionUser->getStandardAddress();
        $hasDeliveryAddress = $Order->hasDeliveryAddress();

        $isSameAddress = function (QUI\Users\Address $a, QUI\Users\Address $b) {
            if (
                $a->getAttribute('firstname') === $b->getAttribute('firstname')
                && $a->getAttribute('lastname') === $b->getAttribute('lastname')
                && $a->getAttribute('street_no') === $b->getAttribute('street_no')
                && $a->getAttribute('zip') === $b->getAttribute('zip')
                && $a->getAttribute('city') === $b->getAttribute('city')
            ) {
                return true;
            }

            return false;
        };

        // check addresses
        if (method_exists($SessionUser, 'getAddressList')) {
            $addresses = $SessionUser->getAddressList();

            foreach ($addresses as $Address) {
                if ($isSameAddress($InvoiceAddress, $Address)) {
                    $Order->setInvoiceAddress($Address);
                    $Order->save(QUI::getUsers()->getSystemUser());

                    return $Checkout->orderWithCosts();
                }
            }
        }

        // if default address is empty, we set it
        if (
            $DefaultAddress->getAttribute('firstname') === ''
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

            $DefaultAddress->save(QUI::getUsers()->getSystemUser());
            $Order->setInvoiceAddress($DefaultAddress);
            $Order->save(QUI::getUsers()->getSystemUser());
        } elseif (
            $DefaultAddress->getAttribute('firstname') === ''
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
            $DefaultAddress->save(QUI::getUsers()->getSystemUser());
        } elseif (method_exists($SessionUser, 'addAddress') && !$userIsGuest) {
            // add new address
            $NewAddress = $SessionUser->addAddress($InvoiceAddress->getAttributes());
            $Order->setInvoiceAddress($NewAddress);
        }

        $Order->save(QUI::getUsers()->getSystemUser());

        return $Checkout->orderWithCosts();
    },
    ['orderHash']
);
