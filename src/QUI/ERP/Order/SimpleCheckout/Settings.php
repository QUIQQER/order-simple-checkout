<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;

/**
 * Main helper for the simple checkout settings.
 */
class Settings extends QUI\Utils\Singleton
{
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
