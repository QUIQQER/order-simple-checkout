<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Order\Guest\GuestOrder;
use QUI\ERP\Order\Guest\GuestOrderUser;
use QUI\ERP\Order\SimpleCheckout\Checkout;
use QUI\Mail\Mailer;

final class GuestEmailCheckTest extends TestCase
{
    private array $createdUsers = [];
    private mixed $originalGuestEmail;
    private mixed $originalRepeatGuests;
    private bool $originalDisableMail;

    protected function setUp(): void
    {
        $this->originalGuestEmail = QUI::getSession()->get(GuestOrder::EMAIL);
        $this->originalRepeatGuests = QUI::getPackage('quiqqer/order-guestorder')
            ->getConfig()?->getValue('guestorder', 'prevent_duplicate_guest_order_registration');
        $this->originalDisableMail = Mailer::$DISABLE_MAIL_SENDING;
        Mailer::$DISABLE_MAIL_SENDING = true;
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUsers as $uuid) {
            QUI::getUsers()->get($uuid)->delete(QUI::getUsers()->getSystemUser());
        }

        if ($this->originalGuestEmail === false) {
            QUI::getSession()->remove(GuestOrder::EMAIL);
        } else {
            QUI::getSession()->set(GuestOrder::EMAIL, $this->originalGuestEmail);
        }

        QUI::getPackage('quiqqer/order-guestorder')->getConfig()?->setValue(
            'guestorder',
            'prevent_duplicate_guest_order_registration',
            $this->originalRepeatGuests
        );
        Mailer::$DISABLE_MAIL_SENDING = $this->originalDisableMail;
    }

    #[DataProvider('repeatGuestModes')]
    public function testKnownEmailRequiresLoginEvenWhenRepeatGuestOrdersAreEnabled(bool $repeatGuests): void
    {
        QUI::getPackage('quiqqer/order-guestorder')->getConfig()?->setValue(
            'guestorder',
            'prevent_duplicate_guest_order_registration',
            (int)$repeatGuests
        );
        $ExistingUser = $this->createUser();
        $email = $ExistingUser->getAttribute('email');
        $Checkout = $this->createGuestCheckout();
        try {
            $Checkout->setGuestEmail($email);
            self::fail('An existing account must require login.');
        } catch (QUI\Exception $Exception) {
            self::assertSame(401, $Exception->getCode());
            self::assertTrue($Exception->getAttribute('authenticationRequired'));
        }

        self::assertFalse(QUI::getUsers()->usernameExists($email));
        self::assertSame($email, QUI::getSession()->get(GuestOrder::EMAIL));
    }

    public static function repeatGuestModes(): array
    {
        return [[false], [true]];
    }

    public function testSubmissionRechecksAnEmailThatWasAvailableWhenEntered(): void
    {
        $Checkout = $this->createGuestCheckout();
        $Checkout->expects(self::never())->method('getOrder');
        $email = 'phpunit-checkout-late-' . bin2hex(random_bytes(8)) . '@example.test';
        $Checkout->setGuestEmail($email);
        $this->createUser($email);

        $this->expectException(QUI\Exception::class);
        $this->expectExceptionCode(401);

        $Checkout->orderWithCosts();
    }

    public function testAnEmptyDuplicateUsernameCannotHideTheAccountOwningTheEmail(): void
    {
        $User = $this->createUser('puc-' . bin2hex(random_bytes(6)) . '@example.test');
        $email = $User->getAttribute('email');
        $Shadow = QUI::getUsers()->createChild($email, QUI::getUsers()->getSystemUser());
        $this->createdUsers[] = $Shadow->getUUID();
        $Checkout = $this->createGuestCheckout();

        $this->expectException(QUI\Exception::class);
        $this->expectExceptionCode(401);

        $Checkout->setGuestEmail($email);
    }

    public function testAnEmailUsedByAnInactiveAccountIsRejectedBeforeSubmission(): void
    {
        $User = $this->createUser(active: false);
        $Checkout = $this->createGuestCheckout();
        $Checkout->getUser()->setAttribute('email', $User->getAttribute('email'));
        $Checkout->expects(self::never())->method('getOrder');

        $this->expectException(QUI\Exception::class);
        $this->expectExceptionCode(409);

        $Checkout->orderWithCosts();
    }

    public function testSubmissionRejectsMissingGuestEmailBeforeLoadingAnOrder(): void
    {
        $Checkout = $this->createGuestCheckout();
        $Checkout->getUser()->setAttribute('email', '');
        $Checkout->expects(self::never())->method('getOrder');

        $this->expectException(QUI\Exception::class);

        $Checkout->orderWithCosts();
    }

    public function testAnAuthenticatedCustomerCanUseTheirExistingEmail(): void
    {
        $User = $this->createUser();
        QUI::getUsers()->withSessionUser($User, static function (): void {
            (new Checkout())->checkGuestEmail();
        });

        self::assertTrue(QUI::getUsers()->emailExists($User->getAttribute('email')));
    }

    private function createGuestCheckout(): \PHPUnit\Framework\MockObject\MockObject&Checkout
    {
        $Checkout = $this->getMockBuilder(Checkout::class)
            ->onlyMethods(['getUser', 'getOrder'])
            ->getMock();
        $Checkout->method('getUser')->willReturn(new GuestOrderUser());

        return $Checkout;
    }

    private function createUser(?string $email = null, bool $active = true): QUI\Interfaces\Users\User
    {
        $username = 'phpunit-checkout-email-' . bin2hex(random_bytes(8));
        $SystemUser = QUI::getUsers()->getSystemUser();
        $User = QUI::getUsers()->createChild($username, $SystemUser);
        $this->createdUsers[] = $User->getUUID();
        $User->setAttribute('email', $email ?? $username . '@example.test');
        $User->save($SystemUser);

        if ($active) {
            $User->setPassword(bin2hex(random_bytes(24)), $SystemUser);
            $User->activate('', $SystemUser);
        }

        return $User;
    }
}
