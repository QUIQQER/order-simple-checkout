<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\Exception as OrderException;
use QUI\ERP\Address;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\Settings;
use QUI\ERP\Order\SimpleCheckout\Checkout;

final class CheckoutTest extends TestCase
{
    public function testConstructorProvidesModernCheckoutDefaults(): void
    {
        $Checkout = new Checkout();

        self::assertSame(true, $Checkout->getAttribute('showBasketLink'));
        self::assertSame(false, $Checkout->getAttribute('showEmail'));
        self::assertSame(
            'package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout',
            $Checkout->getAttribute('data-qui')
        );
    }

    public function testBodyPreparesCheckoutControlsForConfiguredTemplate(): void
    {
        $Articles = $this->createStub(ArticleList::class);
        $Articles->method('count')->willReturn(1);

        $Order = $this->createStub(OrderInProcess::class);
        $Order->method('getArticles')->willReturn($Articles);

        $Checkout = $this->getMockBuilder(Checkout::class)
            ->setConstructorArgs([[
                'template' => dirname(__DIR__) . '/fixtures/checkout.html',
                'showBasketLink' => false
            ]])
            ->onlyMethods(['getOrder', 'getUser'])
            ->getMock();
        $Checkout->method('getOrder')->willReturn($Order);
        $Checkout->method('getUser')->willReturn($this->createStub(\QUI\ERP\User::class));

        self::assertStringContainsString('checkout-test-fixture', $Checkout->getBody());
    }

    public function testMissingOrderReportsPaymentAndRequiredAddress(): void
    {
        $Checkout = $this->getMockBuilder(Checkout::class)
            ->onlyMethods(['getOrder'])
            ->getMock();
        $Checkout->method('getOrder')->willReturn(null);

        $missing = $Checkout->gatherMissingOrderDetails();

        self::assertContains('payment', $missing);

        if (
            !class_exists('QUI\\ERP\\Accounting\\Invoice\\Utils\\Invoice')
            || \QUI\ERP\Accounting\Invoice\Utils\Invoice::addressRequirement()
        ) {
            self::assertContains('address', $missing);
        }
    }

    public function testMissingOrderIsInvalid(): void
    {
        $Checkout = $this->getMockBuilder(Checkout::class)
            ->onlyMethods(['getOrder'])
            ->getMock();
        $Checkout->method('getOrder')->willReturn(null);

        self::assertFalse($Checkout->isValid());
    }

    public function testCompleteOrderIsValidAndHasNoMissingDetails(): void
    {
        $Address = $this->createStub(Address::class);
        $Address->method('getAttribute')->willReturnCallback(
            static fn (string $name): mixed => match ($name) {
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
                'street_no' => 'Example Street 1',
                'city' => 'Example City',
                'country' => 'DE',
                default => null
            }
        );

        $Order = $this->createStub(OrderInProcess::class);
        $Order->method('getInvoiceAddress')->willReturn($Address);
        $Order->method('getPayment')->willReturn($this->createStub(Payment::class));

        if (\QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
            $Order->method('getShipping')->willReturn(
                $this->createStub(\QUI\ERP\Shipping\Types\ShippingEntry::class)
            );
        }

        $Checkout = $this->getMockBuilder(Checkout::class)
            ->onlyMethods(['getOrder'])
            ->getMock();
        $Checkout->method('getOrder')->willReturn($Order);

        self::assertTrue($Checkout->isValid());
        self::assertSame([], $Checkout->gatherMissingOrderDetails());
    }

    public function testOrderDependentRenderHelpersReturnStrings(): void
    {
        $Checkout = $this->getMockBuilder(Checkout::class)
            ->onlyMethods(['getOrder'])
            ->getMock();
        $Checkout->method('getOrder')->willReturn(null);

        self::assertIsString($Checkout->getShipping());
        self::assertIsString($Checkout->getPayments());
        self::assertStringContainsString('quiqqer-simple-checkout-basket__empty', $Checkout->getBasket());
    }

    public function testDeliveryHelperRendersCompleteAddress(): void
    {
        $attributes = [
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'street_no' => 'Example Street 1',
            'zip' => '12345',
            'city' => 'Example City',
            'country' => 'DE'
        ];
        $Address = $this->createStub(Address::class);
        $Address->method('getAttributes')->willReturn($attributes);
        $Address->method('getAttribute')->willReturnCallback(
            static fn (string $name): mixed => $attributes[$name] ?? null
        );

        $Order = $this->createStub(OrderInProcess::class);
        $Order->method('getInvoiceAddress')->willReturn($Address);

        $Checkout = $this->getMockBuilder(Checkout::class)
            ->onlyMethods(['getOrder'])
            ->getMock();
        $Checkout->method('getOrder')->willReturn($Order);

        self::assertStringContainsString('data-name="address-container"', $Checkout->getDelivery());
    }

    public function testOrderingWithoutOrderIsRejected(): void
    {
        $Checkout = $this->getMockBuilder(Checkout::class)
            ->onlyMethods(['getOrder'])
            ->getMock();
        $Checkout->method('getOrder')->willReturn(null);

        $this->expectException(OrderException::class);

        $Checkout->orderWithCosts();
    }

    public function testReturningPaymentProcedureKeepsOrderInProcess(): void
    {
        $Settings = Settings::getInstance();
        $originalProcedure = $Settings->get('order', 'failedPaymentProcedure');
        $orderHash = 'phpunit-simple-checkout-returning';

        try {
            $Settings->set('order', 'failedPaymentProcedure', 'returning');

            $Order = $this->createMock(OrderInProcess::class);
            $Order->method('getPayment')->willReturn(null);
            $Order->method('getUUID')->willReturn($orderHash);
            $Order->expects(self::exactly(2))->method('setData');
            $Order->expects(self::once())->method('save');

            $Checkout = $this->getMockBuilder(Checkout::class)
                ->onlyMethods(['getOrder', 'getOrderProcessStep'])
                ->getMock();
            $Checkout->method('getOrder')->willReturn($Order);
            $Checkout->method('getOrderProcessStep')->willReturn(['step' => 'Processing']);

            self::assertSame(['step' => 'Processing'], $Checkout->orderWithCosts());
            self::assertSame($orderHash, $Checkout->getAttribute('orderHash'));
        } finally {
            $Settings->set('order', 'failedPaymentProcedure', $originalProcedure);
            \QUI::getSession()->set('termsAndConditions-' . $orderHash, null);
        }
    }

    public function testExecutePaymentProcedureCreatesOrderImmediately(): void
    {
        $Settings = Settings::getInstance();
        $originalProcedure = $Settings->get('order', 'failedPaymentProcedure');
        $orderHash = 'phpunit-simple-checkout-execute';

        try {
            $Settings->set('order', 'failedPaymentProcedure', 'execute');

            $CreatedOrder = $this->createMock(Order::class);
            $CreatedOrder->method('getUUID')->willReturn($orderHash);
            $CreatedOrder->expects(self::once())->method('setData')->with('orderedWithCosts', true);
            $CreatedOrder->expects(self::once())->method('save');

            $OrderInProcess = $this->createMock(OrderInProcess::class);
            $OrderInProcess->method('getPayment')->willReturn(null);
            $OrderInProcess->expects(self::once())->method('createOrder')->willReturn($CreatedOrder);

            $Checkout = $this->getMockBuilder(Checkout::class)
                ->onlyMethods(['getOrder', 'getOrderProcessStep'])
                ->getMock();
            $Checkout->method('getOrder')->willReturn($OrderInProcess);
            $Checkout->method('getOrderProcessStep')->willReturn(['step' => 'Processing']);

            self::assertSame(['step' => 'Processing'], $Checkout->orderWithCosts());
            self::assertSame($orderHash, $Checkout->getAttribute('orderHash'));
        } finally {
            $Settings->set('order', 'failedPaymentProcedure', $originalProcedure);
            \QUI::getSession()->set('termsAndConditions-' . $orderHash, null);
        }
    }

    public function testProcessOrderRequiresOrderHash(): void
    {
        $Checkout = new Checkout();

        $this->expectException(OrderException::class);

        $Checkout->getProcessOrder();
    }

    public function testCheckoutUsesSessionUser(): void
    {
        self::assertSame(\QUI::getUserBySession(), (new Checkout())->getUser());
    }
}
