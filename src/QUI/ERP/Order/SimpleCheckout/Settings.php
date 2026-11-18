<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;

/**
 * Main helper for the simple checkout settings.
 */
class Settings extends QUI\Utils\Singleton
{
    public static function isLoginRequired(): bool
    {
        return (bool)self::getConfig()->getValue('orderSimpleCheckout', 'requireLogin');
    }

    public static function isGuestCheckoutEnabled(): bool
    {
        if (
            self::isLoginRequired()
            || !QUI::getPackageManager()->isInstalled('quiqqer/order-guestorder')
        ) {
            return false;
        }

        $GuestConfig = QUI::getPackage('quiqqer/order-guestorder')->getConfig();

        return $GuestConfig?->getValue('guestorder', 'type') === 'noRegistration';
    }

    public static function startGuestCheckout(): void
    {
        if (
            !QUI::isFrontend()
            || !self::isGuestCheckoutEnabled()
            || !QUI::getUsers()->isNobodyUser(QUI::getUserBySession())
        ) {
            return;
        }

        QUI\ERP\Order\Guest\GuestOrder::setGuestOrderFlag();
        QUI::getUsers()->resetSessionUser();
    }

    /**
     * Return the required simple checkout package configuration.
     *
     * @throws QUI\Exception
     */
    public static function getConfig(): QUI\Config
    {
        $Config = QUI::getPackage('quiqqer/order-simple-checkout')->getConfig();

        if ($Config === null) {
            throw new QUI\Exception('Simple checkout configuration is not available.');
        }

        return $Config;
    }
}
