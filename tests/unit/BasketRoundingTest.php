<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Unit;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\ArticleInterface;
use QUI\ERP\Accounting\ArticleView;
use QUI\ERP\Accounting\Calc;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Money\Price;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Types\AbstractType;
use QUI\Interfaces\Users\User;
use QUI\Projects\Media\Image;
use ReflectionProperty;

final class BasketRoundingTest extends TestCase
{
    private string $originalLanguage;
    private array $originalProducts;

    protected function setUp(): void
    {
        $this->originalLanguage = QUI::getLocale()->getCurrent();
        QUI::getLocale()->setCurrent('de');
        $Products = new ReflectionProperty(Products::class, 'list');
        $this->originalProducts = $Products->getValue();

        $Image = $this->createStub(Image::class);
        $Image->method('getSizeCacheUrl')->willReturn('/rounding-test.png');
        $Product = $this->createStub(AbstractType::class);
        $Product->method('getImage')->willReturn($Image);
        $Products->setValue(null, array_replace($this->originalProducts, [
            123 => $Product
        ]));
    }

    protected function tearDown(): void
    {
        QUI::getLocale()->setCurrent($this->originalLanguage);
        (new ReflectionProperty(Products::class, 'list'))->setValue(null, $this->originalProducts);
    }

    public static function basketVariants(): iterable
    {
        foreach (['Basket.ArticleList.html', 'Basket.ForHeader.html'] as $template) {
            foreach ([-1, 123] as $articleId) {
                foreach ([1, 2] as $quantity) {
                    foreach ([false, true] as $net) {
                        $name = $template . ', article ' . $articleId . ', quantity ' . $quantity;
                        $name .= $net ? ', net' : ', gross';

                        yield $name => [
                            $template,
                            $articleId,
                            $quantity,
                            $net
                        ];
                    }
                }
            }
        }
    }

    #[DataProvider('basketVariants')]
    public function testDisplayedUnitPriceMatchesTheCalculatedArticlePrice(
        string $template,
        int $articleId,
        int $quantity,
        bool $net
    ): void {
        $Locale = QUI::getLocale();
        $Currency = new Currency([
            'currency' => 'EUR',
            'rate' => 1,
            'autoupdate' => false,
            'precision' => 2
        ], $Locale);
        $User = $this->createStub(User::class);
        $User->method('getAttribute')->willReturnCallback(
            static function (string $key) use ($net): ?int {
                if ($key !== 'RUNTIME_NETTO_BRUTTO_STATUS') {
                    return null;
                }

                return $net ? QUI\ERP\Utils\User::IS_NETTO_USER : QUI\ERP\Utils\User::IS_BRUTTO_USER;
            }
        );
        $InputPrice = $this->createStub(Price::class);
        $InputPrice->method('value')->willReturn(8.395);
        $InputPrice->method('getValue')->willReturn(8.395);
        $Article = $this->createStub(ArticleInterface::class);
        $Article->method('getCurrency')->willReturn($Currency);
        $Article->method('getUnitPriceUnRounded')->willReturn($InputPrice);
        $Article->method('getVat')->willReturn(19);
        $Article->method('getQuantity')->willReturn($quantity);
        $Article->method('getPrice')->willReturn($InputPrice);

        $Calc = $this->getMockBuilder(Calc::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCurrency'])
            ->getMock();
        $Calc->method('getCurrency')->willReturn($Currency);
        $Calc->setUser($User);
        $Calc->setLocale($Locale);
        $calculated = null;
        $Calc->calcArticlePrice($Article, static function (array $data) use (&$calculated): void {
            $calculated = $data;
        });

        self::assertNotNull($calculated);
        $expectedUnit = $net ? 8.40 : 9.99;
        $expectedSum = $expectedUnit * $quantity;
        self::assertSame($expectedUnit, $calculated['price']);
        self::assertEqualsWithDelta($expectedSum, $calculated['sum'], 0.000001);

        $attributes = [
            'id' => $articleId,
            'title' => 'Rounding test',
            'articleNo' => 'ROUNDING-23',
            'calculated' => $calculated,
            'quantity' => $quantity,
            'displayPrice' => true
        ];
        $View = $this->createStub(ArticleView::class);
        $View->method('getAttribute')->willReturnCallback(
            static fn (string $name): mixed => $attributes[$name] ?? null
        );
        $View->method('getImageUrl')->willReturn('/rounding-test.png');
        $View->method('getQuantityUnit')->willReturn('Stück');

        $Engine = QUI::getTemplateManager()->getEngine();

        if (method_exists($Engine, 'getSmarty')) {
            $Engine->getSmarty()->registerClass(Products::class, Products::class);
        }

        $Engine->assign([
            'articles' => [$View],
            'Currency' => $Currency,
            'Locale' => $Locale,
            'calculations' => [
                'sum' => $Currency->format($calculated['sum']),
                'subSum' => $Currency->format($calculated['sum'])
            ],
            'vatArray' => [],
            'priceFactors' => [],
            'grandTotal' => [],
            'showExchangeRate' => false,
            'disableProductLinks' => true
        ]);
        $templatePath = dirname(__DIR__, 2) . '/src/QUI/ERP/Order/SimpleCheckout/' . $template;
        $html = $Engine->fetch($templatePath);
        $Dom = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);

        try {
            $Dom->loadHTML('<meta charset="UTF-8">' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        $XPath = new DOMXPath($Dom);
        $unit = $XPath->evaluate('normalize-space(//div[@class="quantity"])');
        $sum = $XPath->evaluate('normalize-space(//span[contains(@class,"articleData__sumValue")])');

        self::assertStringContainsString(number_format($expectedUnit, 2, ',', '.'), $unit);
        self::assertStringContainsString(number_format($expectedSum, 2, ',', '.'), $sum);
    }
}
