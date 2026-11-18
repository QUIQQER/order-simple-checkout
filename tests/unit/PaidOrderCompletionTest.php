<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\SimpleCheckout\Checkout;

final class PaidOrderCompletionTest extends TestCase
{
    public function testSuccessfulPaymentCannotSubmitAnUnconfirmedDraft(): void
    {
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getDataEntry')->with('orderedWithCosts')->willReturn(null);
        $Order->expects(self::never())->method('getPayment');
        $Order->expects(self::never())->method('createOrder');
        $Checkout = $this->getMockBuilder(Checkout::class)
            ->onlyMethods(['checkGuestEmail'])
            ->getMock();
        $Checkout->expects(self::never())->method('checkGuestEmail');

        self::assertSame($Order, $Checkout->completePaidOrder($Order));
    }

    public function testAlreadyConvertedDraftCannotCreateAnotherOrder(): void
    {
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getDataEntry')->willReturn(true);
        $Order->method('getOrderId')->willReturn(123);
        $Order->expects(self::never())->method('createOrder');

        self::assertSame($Order, (new Checkout())->completePaidOrder($Order));
    }

    public function testUnsuccessfulPaymentKeepsTheSubmittedOrderInProcess(): void
    {
        $Payment = $this->createStub(Payment::class);
        $Payment->method('isSuccessful')->willReturn(false);
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getDataEntry')->willReturn(true);
        $Order->method('getPayment')->willReturn($Payment);
        $Order->expects(self::never())->method('createOrder');

        self::assertSame($Order, (new Checkout())->completePaidOrder($Order));
    }

    public function testExistingEmailStopsAutomaticCompletionBeforeAnOrderIsCreated(): void
    {
        $Payment = $this->createStub(Payment::class);
        $Payment->method('isSuccessful')->willReturn(true);
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getDataEntry')->willReturn(true);
        $Order->method('getPayment')->willReturn($Payment);
        $Order->expects(self::never())->method('createOrder');
        $Checkout = $this->getMockBuilder(Checkout::class)
            ->onlyMethods(['checkGuestEmail'])
            ->getMock();
        $Checkout->expects(self::once())->method('checkGuestEmail')
            ->willThrowException(new QUI\Exception('Login required', 401));

        $this->expectException(QUI\Exception::class);
        $this->expectExceptionCode(401);

        $Checkout->completePaidOrder($Order);
    }

    public function testSubmittedPaidOrderIsCompletedOnlyAfterTheEmailCheckPasses(): void
    {
        $checked = false;
        $CreatedOrder = $this->createStub(Order::class);
        $Payment = $this->createStub(Payment::class);
        $Payment->method('isSuccessful')->willReturn(true);
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getDataEntry')->willReturn(true);
        $Order->method('getPayment')->willReturn($Payment);
        $Order->expects(self::once())->method('createOrder')->willReturnCallback(
            static function () use (&$checked, $CreatedOrder): Order {
                self::assertTrue($checked);

                return $CreatedOrder;
            }
        );
        $Checkout = $this->getMockBuilder(Checkout::class)
            ->onlyMethods(['checkGuestEmail'])
            ->getMock();
        $Checkout->expects(self::once())->method('checkGuestEmail')->willReturnCallback(
            static function () use (&$checked): void {
                $checked = true;
            }
        );

        self::assertSame($CreatedOrder, $Checkout->completePaidOrder($Order));
    }
}
