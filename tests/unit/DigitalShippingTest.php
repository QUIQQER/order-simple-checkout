<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Address;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\SimpleCheckout\Checkout;
use QUI\ERP\Order\SimpleCheckout\Steps\CheckoutShipping;
use QUI\ERP\Plans\PlanProduct;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Types\DigitalProduct;
use QUI\ERP\Products\Product\Types\Product;
use ReflectionProperty;

final class DigitalShippingTest extends TestCase
{
    private array $originalProducts;

    protected function setUp(): void
    {
        $this->originalProducts = (new ReflectionProperty(Products::class, 'list'))->getValue();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Products::class, 'list'))->setValue(null, $this->originalProducts);
    }

    #[DataProvider('productTypes')]
    public function testShippingRequirementUsesEveryProductType(array $types, bool $digitalOnly): void
    {
        $Order = $this->createOrder($types);
        $Checkout = $this->createCheckout($Order);

        self::assertSame($digitalOnly, $Checkout->hasOnlyDigitalProducts());

        if (QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
            self::assertSame(!$digitalOnly, in_array('shipping', $Checkout->gatherMissingOrderDetails(), true));
            self::assertSame($digitalOnly, $Checkout->isValid());
        }
    }

    public static function productTypes(): array
    {
        return [
            'digital product' => [[DigitalProduct::class], true],
            'subscription' => [[PlanProduct::class], true],
            'several digital products' => [[DigitalProduct::class, PlanProduct::class], true],
            'physical product' => [[Product::class], false],
            'digital then physical' => [[DigitalProduct::class, Product::class], false],
            'physical then digital' => [[Product::class, DigitalProduct::class], false],
            'empty order' => [[], false],
            'custom article' => [[null], false],
            'digital and custom article' => [[DigitalProduct::class, null], false]
        ];
    }

    public function testMissingOrderDoesNotBypassShipping(): void
    {
        self::assertFalse($this->createCheckout(null)->hasOnlyDigitalProducts());
    }

    public function testShippingStepSkipsAddressPromptAndShippingLookupForDigitalOrder(): void
    {
        $Order = $this->createOrder([PlanProduct::class]);
        $Order->method('getInvoiceAddress')->willReturn($this->createStub(Address::class));
        $Checkout = $this->createCheckout($Order);

        $html = (new CheckoutShipping($Checkout))->getBody();

        self::assertStringContainsString('data-name="shipping-not-required"', $html);
        self::assertStringNotContainsString('order-simple-checkout-shipping--info', $html);
        self::assertStringNotContainsString('message-error', $html);
    }

    public function testProductChangesAreRecheckedWithinTheSameCheckout(): void
    {
        $DigitalOrder = $this->createOrder([DigitalProduct::class]);
        $MixedOrder = $this->createOrder([DigitalProduct::class, Product::class]);
        $Checkout = new Checkout();

        self::assertTrue($Checkout->hasOnlyDigitalProducts($DigitalOrder));
        self::assertFalse($Checkout->hasOnlyDigitalProducts($MixedOrder));
        self::assertTrue($Checkout->hasOnlyDigitalProducts($DigitalOrder));
    }

    private function createCheckout(?OrderInProcess $Order): Checkout
    {
        $Checkout = $this->getMockBuilder(Checkout::class)
            ->onlyMethods(['getOrder', 'getUser'])
            ->getMock();
        $Checkout->method('getOrder')->willReturn($Order);
        $Checkout->method('getUser')->willReturn(QUI::getUsers()->getSystemUser());

        return $Checkout;
    }

    private function createOrder(array $types): OrderInProcess
    {
        $products = $this->originalProducts;
        $articles = [];

        foreach ($types as $index => $type) {
            $id = $type === null ? 0 : 42424000 + $index;

            if ($type !== null) {
                $products[$id] = $this->createStub($type);
            }

            $articles[] = new Article(['id' => $id]);
        }

        (new ReflectionProperty(Products::class, 'list'))->setValue(null, $products);
        $Articles = $this->createStub(ArticleList::class);
        $Articles->method('getArticles')->willReturn($articles);

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
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getInvoiceAddress')->willReturn($Address);
        $Order->method('getPayment')->willReturn($this->createStub(Payment::class));
        $Order->method('getShipping')->willReturn(null);

        return $Order;
    }
}
