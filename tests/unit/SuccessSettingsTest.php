<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\SimpleCheckout\SuccessSettings;

final class SuccessSettingsTest extends TestCase
{
    public function testDefaultsDoNotEnableCompletionActions(): void
    {
        self::assertSame(SuccessSettings::DEFAULTS, SuccessSettings::normalize([]));
    }

    public function testTrustedPhpContentKeepsItsMarkup(): void
    {
        $content = '<p class="thanks">Vielen Dank! <strong>Weiter:</strong></p>'
            . '<a class="button" aria-label="Zum Konto" href="/konto">Mein Konto</a>';
        $settings = SuccessSettings::normalize([
            'successContent' => $content
        ]);

        self::assertSame($content, $settings['successContent']);
        self::assertSame($settings, SuccessSettings::normalize($settings));
    }

    public function testFrontendRequestsCannotMarkHtmlAsTrusted(): void
    {
        $content = '<img src=x onerror="alert(1)"><script>alert(2)</script>';
        $settings = SuccessSettings::frontendSettings([
            'successContent' => $content,
            'successContentSource' => 'php',
            'successUrl' => '/konto',
            'successRedirectDelay' => 5
        ]);

        self::assertSame([
            'successContent' => $content,
            'successUrl' => '/konto',
            'successRedirectDelay' => 5,
            'successContentSource' => 'frontend'
        ], $settings);
    }

    public function testFrontendRedirectChangesPreservePhpContent(): void
    {
        $stored = SuccessSettings::normalize([
            'successContent' => '<a href="/konto">Konto</a>',
            'successContentSource' => 'php'
        ]);
        $expected = array_replace($stored, [
            'successUrl' => '/konto',
            'successRedirectDelay' => 5
        ]);
        $Customer = $this->createStub(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn(QUI::getUserBySession()->getUUID());
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getDataEntry')->willReturnCallback(
            static fn (string $key): mixed => $key === SuccessSettings::DATA_KEY ? $stored : null
        );
        $Order->expects(self::once())->method('setData')->with(SuccessSettings::DATA_KEY, $expected);
        $Order->expects(self::once())->method('save');
        $settings = [
            'successUrl' => '/konto',
            'successRedirectDelay' => 5,
            'successContentSource' => 'frontend'
        ];

        self::assertSame($expected, SuccessSettings::configureFrontend($Order, $settings));
    }

    #[DataProvider('redirectTargets')]
    public function testRedirectTargetsAreValidated(mixed $url, string|false $expected): void
    {
        self::assertSame($expected, SuccessSettings::normalize(['successUrl' => $url])['successUrl']);
    }

    public static function redirectTargets(): iterable
    {
        yield ['/konto?order=123#details', '/konto?order=123#details'];
        yield ['https://example.com/account', 'https://example.com/account'];
        yield ['http://example.com/account', 'http://example.com/account'];
        yield [false, false];
        yield ['', false];
        yield ['javascript:alert(1)', false];
        yield ['data:text/html,test', false];
        yield ['//example.com', false];
        yield ['/\\example.com', false];
        yield ["/\n/example.com", false];
        yield ['account', false];
        yield [[], false];
    }

    #[DataProvider('redirectDelays')]
    public function testRedirectDelaysAllowZeroAndRejectInvalidValues(mixed $delay, int|false $expected): void
    {
        self::assertSame(
            $expected,
            SuccessSettings::normalize(['successRedirectDelay' => $delay])['successRedirectDelay']
        );
    }

    public static function redirectDelays(): iterable
    {
        yield [false, false];
        yield [null, false];
        yield ['', false];
        yield [0, 0];
        yield ['0', 0];
        yield [10, 10];
        yield ['10', 10];
        yield [86400, 86400];
        yield [86401, false];
        yield [-1, false];
        yield [1.5, false];
        yield [true, false];
        yield ['soon', false];
    }

    public function testSettingsAreSavedBeforePayment(): void
    {
        $settings = SuccessSettings::normalize([
            'successContent' => '<a href="/konto">Konto</a>',
            'successContentSource' => 'php'
        ]);
        $Customer = $this->createStub(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn(QUI::getUserBySession()->getUUID());
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->expects(self::once())->method('setData')->with(SuccessSettings::DATA_KEY, $settings);
        $Order->expects(self::once())->method('save')->with(QUI::getUserBySession());

        self::assertSame($settings, SuccessSettings::configure($Order, $settings));
    }

    public function testDefaultsOnReturnKeepSavedSettings(): void
    {
        $settings = ['successContent' => '<a href="/konto">Konto</a>'] + SuccessSettings::DEFAULTS;
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getDataEntry')->willReturnCallback(
            static fn (string $key): mixed => $key === SuccessSettings::DATA_KEY ? $settings : null
        );
        $Order->expects(self::never())->method('setData');
        $Order->expects(self::never())->method('save');

        self::assertSame($settings, SuccessSettings::configure($Order, SuccessSettings::DEFAULTS));
    }

    public function testOtherCustomersCannotChangeCompletionSettings(): void
    {
        $Customer = $this->createStub(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn('another-customer');
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->expects(self::never())->method('setData');
        $Order->expects(self::never())->method('save');

        $this->expectException(QUI\Exception::class);
        $this->expectExceptionCode(403);

        SuccessSettings::configure($Order, ['successContent' => 'Changed']);
    }

    public function testSubmittedOrderSettingsCannotBeOverwritten(): void
    {
        $settings = ['successContent' => 'Original'] + SuccessSettings::DEFAULTS;
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getDataEntry')->willReturnCallback(
            static fn (string $key): mixed => match ($key) {
                SuccessSettings::DATA_KEY => $settings,
                'orderedWithCosts' => true,
                default => null
            }
        );
        $Order->expects(self::never())->method('setData');
        $Order->expects(self::never())->method('save');

        self::assertSame($settings, SuccessSettings::configure($Order, ['successContent' => 'Changed']));
    }

    public function testFinalOrderUsesSavedSettings(): void
    {
        $settings = array_replace(SuccessSettings::DEFAULTS, ['successUrl' => '/konto', 'successRedirectDelay' => 5]);
        $Order = $this->createMock(Order::class);
        $Order->method('getDataEntry')->with(SuccessSettings::DATA_KEY)->willReturn($settings);
        $Order->expects(self::never())->method('setData');
        $Order->expects(self::never())->method('save');

        self::assertSame($settings, SuccessSettings::configure($Order, ['successContent' => 'Changed']));
    }

    public function testFrontendContentIsSavedWithoutTrustingItsSourceMarker(): void
    {
        $content = '<p>Danke!</p><img src=x onerror="alert(1)">';
        $expected = SuccessSettings::normalize(['successContent' => $content]);
        $Customer = $this->createStub(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn(QUI::getUserBySession()->getUUID());
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->expects(self::once())->method('setData')->with(SuccessSettings::DATA_KEY, $expected);
        $Order->expects(self::once())->method('save');

        self::assertSame($expected, SuccessSettings::configureFrontend($Order, [
            'successContent' => $content,
            'successContentSource' => 'php'
        ]));
    }

    public function testPhpDefaultsNeverPromoteSavedFrontendContentToTrustedHtml(): void
    {
        $settings = SuccessSettings::normalize(['successContent' => '<img src=x onerror="alert(1)">']);
        $Order = $this->createMock(OrderInProcess::class);
        $Order->method('getDataEntry')->willReturnCallback(
            static fn (string $key): mixed => $key === SuccessSettings::DATA_KEY ? $settings : null
        );
        $Order->expects(self::never())->method('save');

        self::assertSame($settings, SuccessSettings::configure($Order, SuccessSettings::DEFAULTS));
    }

    public function testLegacyPhpSettingsKeepTheirOriginalRenderingMode(): void
    {
        $settings = [
            'successContent' => '<a href="/konto">Konto</a>',
            'successUrl' => false,
            'successRedirectDelay' => false
        ];
        $Order = $this->createStub(Order::class);
        $Order->method('getDataEntry')->with(SuccessSettings::DATA_KEY)->willReturn($settings);

        self::assertSame($settings + ['successContentSource' => 'php'], SuccessSettings::read($Order));
    }
}
