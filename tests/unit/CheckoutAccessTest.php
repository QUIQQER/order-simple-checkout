<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Order\Guest\GuestOrderUser;
use QUI\ERP\Order\SimpleCheckout\Checkout;
use QUI\ERP\Order\SimpleCheckout\Settings;

final class CheckoutAccessTest extends TestCase
{
    private mixed $originalRequireLogin;
    private mixed $originalGuestType;
    private array $originalSessionValues = [];

    protected function setUp(): void
    {
        $this->originalRequireLogin = Settings::getConfig()->getValue('orderSimpleCheckout', 'requireLogin');
        $GuestConfig = QUI::getPackage('quiqqer/order-guestorder')->getConfig();
        $this->originalGuestType = $GuestConfig?->getValue('guestorder', 'type');
        $sessionKeys = [
            'uid',
            'auth',
            'auth-primary',
            'auth-secondary',
            'guest-order-id',
            QUI\ERP\Order\Guest\GuestOrder::FLAG,
            QUI\ERP\Order\Guest\GuestOrder::EMAIL,
            QUI\ERP\Order\Guest\GuestOrder::CUSTOMER_UUID,
            QUI\ERP\Order\Guest\GuestOrder::CUSTOMER_ID
        ];

        foreach ($sessionKeys as $key) {
            $this->originalSessionValues[$key] = QUI::getSession()->get($key);
        }
    }

    protected function tearDown(): void
    {
        Settings::getConfig()->setValue('orderSimpleCheckout', 'requireLogin', $this->originalRequireLogin);
        QUI::getPackage('quiqqer/order-guestorder')->getConfig()?->setValue(
            'guestorder',
            'type',
            $this->originalGuestType
        );
        foreach ($this->originalSessionValues as $key => $value) {
            if ($value === false) {
                QUI::getSession()->remove($key);
            } else {
                QUI::getSession()->set($key, $value);
            }
        }
    }

    public function testOnlyGuestOrdersWithoutRegistrationEnableDirectCheckout(): void
    {
        Settings::getConfig()->setValue('orderSimpleCheckout', 'requireLogin', 0);
        $GuestConfig = QUI::getPackage('quiqqer/order-guestorder')->getConfig();
        self::assertNotNull($GuestConfig);

        $guestModes = [
            'no' => false,
            'anonymous' => false,
            'noRegistration' => true
        ];

        foreach ($guestModes as $type => $expected) {
            $GuestConfig->setValue('guestorder', 'type', $type);
            self::assertSame($expected, Settings::isGuestCheckoutEnabled(), $type);
        }

        Settings::getConfig()->setValue('orderSimpleCheckout', 'requireLogin', 1);
        self::assertFalse(Settings::isGuestCheckoutEnabled());
    }

    public function testLoginRequirementRejectsBothVisitorsAndExistingGuestSessions(): void
    {
        Settings::getConfig()->setValue('orderSimpleCheckout', 'requireLogin', 1);

        $users = [
            QUI::getUsers()->getNobody(),
            new GuestOrderUser()
        ];
        $methods = [
            'getOrder',
            'getProcessOrder',
            'checkLoginRequirement'
        ];

        foreach ($users as $User) {
            $Checkout = $this->createCheckout($User);

            self::assertTrue($Checkout->requiresLogin());
            self::assertStringContainsString('quiqqer-order-simple-login', $Checkout->getBody());

            foreach ($methods as $method) {
                try {
                    $Checkout->$method();
                    self::fail($method . ' must require login for ' . $User::class);
                } catch (QUI\Exception $Exception) {
                    self::assertSame(
                        QUI::getLocale()->get('quiqqer/order-simple-checkout', 'exception.login.required'),
                        $Exception->getMessage()
                    );
                }
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testGuestSessionStartsAtExplicitEntryInsteadOfControlConstruction(): void
    {
        Settings::getConfig()->setValue('orderSimpleCheckout', 'requireLogin', 0);
        QUI::getPackage('quiqqer/order-guestorder')->getConfig()?->setValue('guestorder', 'type', 'noRegistration');
        QUI::getSession()->remove(QUI\ERP\Order\Guest\GuestOrder::FLAG);

        $authenticationKeys = [
            'uid',
            'auth',
            'auth-primary',
            'auth-secondary'
        ];

        foreach ($authenticationKeys as $key) {
            QUI::getSession()->remove($key);
        }

        QUI::getUsers()->withSessionUser(QUI::getUsers()->getNobody(), static function (): void {
            new Checkout();
            self::assertEmpty(QUI::getSession()->get(QUI\ERP\Order\Guest\GuestOrder::FLAG));
            self::assertTrue(QUI::getUsers()->isNobodyUser(QUI::getUserBySession()));

            Settings::startGuestCheckout();

            self::assertSame(1, QUI::getSession()->get(QUI\ERP\Order\Guest\GuestOrder::FLAG));
            self::assertInstanceOf(GuestOrderUser::class, QUI::getUserBySession());
        });
    }

    public function testLoginRequirementAllowsAuthenticatedUsers(): void
    {
        Settings::getConfig()->setValue('orderSimpleCheckout', 'requireLogin', 1);
        $users = [
            QUI::getUsers()->getSystemUser(),
            $this->createStub(QUI\Users\User::class)
        ];

        foreach ($users as $User) {
            $Checkout = $this->createCheckout($User);

            self::assertFalse($Checkout->requiresLogin());
            $Checkout->checkLoginRequirement();
        }
    }

    public function testDisabledLoginRequirementAllowsGuestSessions(): void
    {
        Settings::getConfig()->setValue('orderSimpleCheckout', 'requireLogin', 0);
        $Checkout = $this->createCheckout(new GuestOrderUser());

        self::assertFalse($Checkout->requiresLogin());
        $Checkout->checkLoginRequirement();
    }

    public function testGuestEmailIsCapturedInCheckoutAndInvalidInputClearsIt(): void
    {
        $Guest = new GuestOrderUser();
        $Checkout = $this->createCheckout($Guest);
        $email = 'phpunit-direct-checkout-' . uniqid() . '@example.test';

        $Checkout->setGuestEmail($email);

        self::assertSame($email, $Guest->getAttribute('email'));
        self::assertSame($email, QUI::getSession()->get(QUI\ERP\Order\Guest\GuestOrder::EMAIL));

        $Checkout->setGuestEmail('invalid');

        self::assertSame('', $Guest->getAttribute('email'));
        self::assertEmpty(QUI::getSession()->get(QUI\ERP\Order\Guest\GuestOrder::EMAIL));
        self::assertFalse($Checkout->isValid());
    }

    private function createCheckout(QUI\Interfaces\Users\User $User): Checkout
    {
        $Checkout = $this->getMockBuilder(Checkout::class)
            ->onlyMethods(['getUser'])
            ->getMock();
        $Checkout->method('getUser')->willReturn($User);

        return $Checkout;
    }
}
