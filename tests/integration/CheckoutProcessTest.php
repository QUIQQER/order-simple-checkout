<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\Factory;
use QUI\ERP\Order\Handler;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\SimpleCheckout\Checkout;
use Throwable;

final class CheckoutProcessTest extends TestCase
{
    private ?string $orderHash = null;

    protected function setUp(): void
    {
        if (!defined('SYSTEM_INTERN')) {
            define('SYSTEM_INTERN', true);
        }

        try {
            $this->getConnection()->fetchOne('SELECT 1');
        } catch (Throwable $Exception) {
            self::markTestSkipped(
                'DB integration test skipped because no database connection is available: ' . $Exception->getMessage()
            );
        }
    }

    protected function tearDown(): void
    {
        if ($this->orderHash !== null) {
            $this->getConnection()->delete(
                Handler::getInstance()->tableOrderProcess(),
                ['hash' => $this->orderHash]
            );
        }

        parent::tearDown();
    }

    public function testCheckoutResolvesAndRendersOrderInProcess(): void
    {
        $Order = Factory::getInstance()->createOrderInProcess(\QUI::getUsers()->getSystemUser());
        $this->orderHash = $Order->getUUID();
        $Checkout = new Checkout(['orderHash' => $this->orderHash]);

        self::assertInstanceOf(OrderInProcess::class, $Checkout->getOrder());
        self::assertSame($this->orderHash, $Checkout->getProcessOrder()->getUUID());

        $step = $Checkout->getOrderProcessStep();

        self::assertSame($this->orderHash, $step['orderHash']);
        self::assertArrayHasKey('html', $step);
        self::assertArrayHasKey('step', $step);
        self::assertArrayHasKey('url', $step);
        self::assertArrayHasKey('hash', $step);
        self::assertSame(0, $step['productCount']);
    }

    private function getConnection(): Connection
    {
        return \QUI::getDataBaseConnection();
    }
}
