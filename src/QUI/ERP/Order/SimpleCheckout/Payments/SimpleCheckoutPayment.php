<?php

/**
 * Simple checkout payment step with conditional recalculation
 */

namespace QUI\ERP\Order\SimpleCheckout\Payments;

use QUI;
use QUI\ERP\Accounting\Payments\Order\Payment as BasePayment;

class SimpleCheckoutPayment extends BasePayment
{
    /**
     * @return string
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $User = QUI::getUserBySession();

        $Order = $this->getOrder();
        $needsRecalc = $Order->getDataEntry('sc_needs_recalc');

        if ($needsRecalc === null || (int)$needsRecalc === 1) {
            $Order->recalculate();
            $Order->setData('sc_needs_recalc', 0);

            if (method_exists($Order, 'save')) {
                $Order->save();
            }
        }

        $Currency = $Order->getCurrency();
        $Customer = $Order->getCustomer();
        $SelectedPayment = $Order->getPayment();
        $payments = $this->getPaymentList();

        foreach ($payments as $PaymentEntry) {
            $PaymentEntry->setAttribute('Order', $Order);
        }

        $Engine->assign([
            'User' => $User,
            'Customer' => $Customer,
            'Currency' => $Currency,
            'SelectedPayment' => $SelectedPayment,
            'payments' => $payments,
            'this' => $this
        ]);

        $baseFile = (new \ReflectionClass(BasePayment::class))->getFileName();
        return $Engine->fetch(dirname($baseFile) . '/Payment.html');
    }
}
