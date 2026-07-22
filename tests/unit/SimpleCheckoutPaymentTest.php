<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\SimpleCheckout\Payments\SimpleCheckoutPayment;

final class SimpleCheckoutPaymentTest extends TestCase
{
    public function testPaymentBodyRecalculatesOrderOnlyWhenRequired(): void
    {
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getDataEntry')->with('sc_needs_recalc')->willReturn(null);
        $Order->expects(self::once())->method('recalculate');
        $Order->expects(self::once())->method('setData')->with('sc_needs_recalc', 0);
        $Order->expects(self::once())->method('save');
        $Order->method('getCurrency')->willReturn(\QUI\ERP\Defaults::getCurrency());
        $Order->method('getCustomer')->willReturn($this->createStub(\QUI\ERP\User::class));
        $Order->method('getPayment')->willReturn(null);

        $Payment = new class (['Order' => $Order]) extends SimpleCheckoutPayment {
            protected function getPaymentList(): array
            {
                return [];
            }
        };

        self::assertIsString($Payment->getBody());
    }

    public function testPaymentBodyKeepsAlreadyRecalculatedOrder(): void
    {
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getDataEntry')->with('sc_needs_recalc')->willReturn(0);
        $Order->expects(self::never())->method('recalculate');
        $Order->expects(self::never())->method('setData');
        $Order->expects(self::never())->method('save');
        $Order->method('getCurrency')->willReturn(\QUI\ERP\Defaults::getCurrency());
        $Order->method('getCustomer')->willReturn($this->createStub(\QUI\ERP\User::class));
        $Order->method('getPayment')->willReturn(null);

        $Payment = new class (['Order' => $Order]) extends SimpleCheckoutPayment {
            protected function getPaymentList(): array
            {
                return [];
            }
        };

        self::assertIsString($Payment->getBody());
    }
}
