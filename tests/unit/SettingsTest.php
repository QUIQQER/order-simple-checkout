<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QUI\Config;
use QUI\ERP\Order\SimpleCheckout\Settings;

final class SettingsTest extends TestCase
{
    public function testGetConfigReturnsPackageConfiguration(): void
    {
        self::assertInstanceOf(Config::class, Settings::getConfig());
    }
}
