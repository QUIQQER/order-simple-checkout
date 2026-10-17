<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
}
