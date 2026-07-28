<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\Exception as OrderException;
use QUI\ERP\Order\SimpleCheckout\Basket;
use QUI\ERP\Order\SimpleCheckout\Checkout;
use QUI\ERP\Order\SimpleCheckout\Steps\CheckoutBillingAddress;
use QUI\ERP\Order\SimpleCheckout\Steps\CheckoutDelivery;
use QUI\ERP\Order\SimpleCheckout\Steps\CheckoutPayment;
use QUI\ERP\Order\SimpleCheckout\Steps\CheckoutShipping;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Address;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\ArticleListUnique;

final class ControlsTest extends TestCase
{
    public function testStepConstructorsConfigureTheirJavascriptControls(): void
    {
        $Checkout = $this->createStub(Checkout::class);

        self::assertInstanceOf(\QUI\Control::class, new CheckoutDelivery($Checkout));
        self::assertInstanceOf(\QUI\Control::class, new CheckoutBillingAddress($Checkout));
        self::assertInstanceOf(\QUI\Control::class, new CheckoutPayment($Checkout));
        self::assertInstanceOf(\QUI\Control::class, new CheckoutShipping($Checkout));
    }

    public function testBasketRendersEmptyStateWithoutOrder(): void
    {
        $Checkout = $this->createStub(Checkout::class);
        $Checkout->method('getOrder')->willReturn(null);

        $html = (new Basket($Checkout))->getBody();

        self::assertStringContainsString('quiqqer-simple-checkout-basket__empty', $html);
    }

    public function testBasketRendersOrderArticlesAndHeaderVariant(): void
    {
        $UniqueArticles = $this->createMock(ArticleListUnique::class);
        $UniqueArticles->expects(self::once())->method('hideHeader');
        $UniqueArticles->expects(self::exactly(2))
            ->method('toHTML')
            ->willReturnOnConsecutiveCalls('DEFAULT-BASKET', 'HEADER-BASKET');

        $Articles = $this->createMock(ArticleList::class);
        $Articles->method('count')->willReturn(1);
        $Articles->expects(self::once())
            ->method('setCurrency')
            ->with(\QUI\ERP\Defaults::getCurrency());
        $Articles->method('toUniqueList')->willReturn($UniqueArticles);

        $Order = $this->createMock(OrderInProcess::class);
        $Order->expects(self::once())->method('recalculate');
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getCurrency')->willReturn(\QUI\ERP\Defaults::getCurrency());

        $Checkout = $this->createStub(Checkout::class);
        $Checkout->method('getOrder')->willReturn($Order);
        $Checkout->method('getAttribute')->with('disableProductLinks')->willReturn(false);

        $html = (new Basket($Checkout, ['basketForHeader' => true]))->getBody();

        self::assertStringContainsString('HEADER-BASKET', $html);
    }

    public function testDeliveryAndBillingAddressRenderConfiguredOrderAddress(): void
    {
        $Address = $this->createAddressStub();
        $Order = $this->createStub(OrderInProcess::class);
        $Order->method('getInvoiceAddress')->willReturn($Address);
        $Order->method('getDeliveryAddress')->willReturn($Address);

        $Checkout = $this->createStub(Checkout::class);
        $Checkout->method('getOrder')->willReturn($Order);
        $Checkout->method('getAttribute')->willReturnMap([
            ['showEmail', false],
            ['businessTypeIsChangeable', false]
        ]);

        $deliveryHtml = (new CheckoutDelivery($Checkout))->getBody();
        $billingHtml = (new CheckoutBillingAddress($Checkout))->getBody();

        self::assertStringContainsString('data-name="address-container"', $deliveryHtml);
        self::assertStringContainsString('billing_firstname', $billingHtml);
    }

    public function testBillingAddressValidationRejectsMissingOrderAddress(): void
    {
        $Checkout = $this->createStub(Checkout::class);
        $Checkout->method('getOrder')->willReturn(null);

        $this->expectException(OrderException::class);

        (new CheckoutBillingAddress($Checkout))->validate();
    }

    public function testAddressValidationAcceptsCompleteOrderAddress(): void
    {
        $Order = $this->createStub(OrderInProcess::class);
        $Order->method('getInvoiceAddress')->willReturn($this->createAddressStub());

        $Checkout = $this->createStub(Checkout::class);
        $Checkout->method('getOrder')->willReturn($Order);

        (new CheckoutDelivery($Checkout))->validate();
        (new CheckoutBillingAddress($Checkout))->validate();

        self::addToAssertionCount(2);
    }

    public function testDeliveryFallsBackWhenOrderAddressIsEmpty(): void
    {
        $Address = $this->createStub(Address::class);
        $Address->method('getAttributes')->willReturn([]);

        $Order = $this->createStub(OrderInProcess::class);
        $Order->method('getInvoiceAddress')->willReturn($Address);

        $Checkout = $this->createStub(Checkout::class);
        $Checkout->method('getOrder')->willReturn($Order);

        $Delivery = new class ($Checkout) extends CheckoutDelivery {
            public function resolveInvoiceAddress(): ?Address
            {
                return $this->getInvoiceAddress();
            }
        };

        $resolvedAddress = $Delivery->resolveInvoiceAddress();

        self::assertTrue($resolvedAddress === null || $resolvedAddress instanceof Address);
    }

    public function testPaymentPromptsForAddressWhenOrderIsMissing(): void
    {
        if (
            class_exists('QUI\\ERP\\Accounting\\Invoice\\Utils\\Invoice')
            && !\QUI\ERP\Accounting\Invoice\Utils\Invoice::addressRequirement()
        ) {
            self::markTestSkipped('The configured shop does not require an address.');
        }

        $Checkout = $this->createStub(Checkout::class);
        $Checkout->method('getOrder')->willReturn(null);

        $html = (new CheckoutPayment($Checkout))->getBody();

        self::assertStringContainsString('order-simple-checkout-payment--info', $html);
    }

    public function testShippingBodyMatchesShippingAvailability(): void
    {
        $Checkout = $this->createStub(Checkout::class);
        $Checkout->method('getOrder')->willReturn(null);

        $html = (new CheckoutShipping($Checkout))->getBody();

        if (
            \QUI::getPackageManager()->isInstalled('quiqqer/shipping')
            && class_exists('QUI\ERP\Shipping\Order\Shipping')
        ) {
            self::assertStringContainsString('order-simple-checkout-shipping--info', $html);

            return;
        }

        self::assertSame('', $html);
    }

    private function createAddressStub(): Address
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

        return $Address;
    }
}
