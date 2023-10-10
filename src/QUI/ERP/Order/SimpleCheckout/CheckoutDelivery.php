<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;
use QUI\Exception;
use QUI\Users\User;

use function dirname;
use function is_string;
use function json_decode;

/**
 * Class CheckoutDelivery
 *
 * @package Your\Package\Namespace
 */
class CheckoutDelivery extends QUI\Control
{
    /**
     * Constructor method for the SimpleCheckoutDelivery class.
     *
     * @param array $attributes Optional attributes to be passed to the parent constructor.
     * @return void
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/CheckoutDelivery.css');
        $this->addCSSClass('quiqqer-simple-checkout-delivery');
        $this->setJavaScriptControl(
            'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckoutDelivery'
        );
    }

    /**
     * Returns the HTML body content for the checkout delivery step.
     *
     * @return string The HTML body content for the checkout delivery step.
     * @throws Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $User = QUI::getUserBySession();

        $isUserB2B = function () use ($User) {
            if (!$User) {
                return '';
            }

            if ($User->getAttribute('quiqqer.erp.isNettoUser') === QUI\ERP\Utils\User::IS_NETTO_USER) {
                return ' selected="selected"';
            }

            if ($User->getAttribute('quiqqer.erp.isNettoUser') !== false) {
                return '';
            }

            if (
                QUI\ERP\Utils\Shop::isB2CPrioritized() ||
                QUI\ERP\Utils\Shop::isOnlyB2C()
            ) {
                return '';
            }

            if (QUI\ERP\Utils\Shop::isB2B()) {
                return ' selected="selected"';
            }

            return '';
        };

        $isB2B = QUI\ERP\Utils\Shop::isB2B();
        $isB2C = QUI\ERP\Utils\Shop::isB2C();
        $isOnlyB2B = QUI\ERP\Utils\Shop::isOnlyB2B();
        $isOnlyB2C = QUI\ERP\Utils\Shop::isOnlyB2C();

        // frontend users address profile settings
        try {
            $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();
            $settings = $Conf->getValue('profile', 'addressFields');

            if (!empty($settings)) {
                $settings = json_decode($settings, true);
            }
        } catch (QUI\Exception $Exception) {
            $settings = [];
        }

        if (empty($settings) || is_string($settings)) {
            $settings = [];
        }

        $settings = QUI\FrontendUsers\Controls\Address\Address::checkSettingsArray($settings);
        $businessTypeIsChangeable = !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B());

        if ($this->existsAttribute('businessTypeIsChangeable')) {
            $businessTypeIsChangeable = $this->getAttribute('businessTypeIsChangeable');
        }

        $Engine->assign([
            'User' => $User,
            'Address' => $this->getInvoiceAddress(),
            'b2bSelected' => $isUserB2B(),
            'isB2C' => $isB2C,
            'isB2B' => $isB2B,
            'isOnlyB2B' => $isOnlyB2B,
            'isOnlyB2C' => $isOnlyB2C,
            'settings' => $settings,
            'businessTypeIsChangeable' => $businessTypeIsChangeable,
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/CheckoutDelivery.html');
    }

    /**
     * Retrieves the invoice address for the current user.
     *
     * @return false|QUI\Users\Address|null
     */
    protected function getInvoiceAddress()
    {
        $User = QUI::getUserBySession();

        if ($User->getAttribute('quiqqer.erp.address')) {
            try {
                return $User->getAddress($User->getAttribute('quiqqer.erp.address'));
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        try {
            /* @var $User User */
            return $User->getStandardAddress();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return null;
    }
}
