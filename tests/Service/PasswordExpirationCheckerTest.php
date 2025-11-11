<?php

namespace GillesG\PasswordExpirationBundle\Tests\Service;

use GillesG\PasswordExpirationBundle\Service\PasswordExpirationChecker;
use GillesG\PasswordExpirationBundle\Tests\Integration\Stubs\User;
use PHPUnit\Framework\TestCase;

class PasswordExpirationCheckerTest extends TestCase
{
    private PasswordExpirationChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new PasswordExpirationChecker();
    }

    public function testPasswordNotExpiredWithinLifetime(): void
    {
        $user = new User();
        $user->setPasswordUpdatedAt(new \DateTime('-30 days'));

        $isExpired = $this->checker->isPasswordExpired($user, 'passwordUpdatedAt', 90);

        self::assertFalse($isExpired);
    }

    public function testPasswordExpiredAfterLifetime(): void
    {
        $user = new User();
        $user->setPasswordUpdatedAt(new \DateTime('-100 days'));

        $isExpired = $this->checker->isPasswordExpired($user, 'passwordUpdatedAt', 90);

        self::assertTrue($isExpired);
    }

    public function testPasswordExactlyAtExpirationBoundary(): void
    {
        $user = new User();
        $user->setPasswordUpdatedAt(new \DateTime('-90 days'));

        $isExpired = $this->checker->isPasswordExpired($user, 'passwordUpdatedAt', 90);

        // After exactly 90 days, the password should not be expired yet
        self::assertFalse($isExpired);
    }

    public function testPasswordExpiredJustAfterBoundary(): void
    {
        $user = new User();
        $user->setPasswordUpdatedAt(new \DateTime('-91 days'));

        $isExpired = $this->checker->isPasswordExpired($user, 'passwordUpdatedAt', 90);

        self::assertTrue($isExpired);
    }

    public function testUserWithoutPasswordField(): void
    {
        $user = new class {
            public function getId(): int
            {
                return 1;
            }
        };

        $isExpired = $this->checker->isPasswordExpired($user, 'passwordUpdatedAt', 90);

        self::assertFalse($isExpired);
    }

    public function testUserWithNullPasswordDate(): void
    {
        $user = new User();
        $user->setPasswordUpdatedAt(null);

        $isExpired = $this->checker->isPasswordExpired($user, 'passwordUpdatedAt', 90);

        self::assertFalse($isExpired);
    }

    public function testCustomFieldName(): void
    {
        $user = new class {
            private ?\DateTimeInterface $customField = null;

            public function getCustomField(): ?\DateTimeInterface
            {
                return $this->customField;
            }

            public function setCustomField(?\DateTimeInterface $date): self
            {
                $this->customField = $date;
                return $this;
            }
        };

        $user->setCustomField(new \DateTime('-100 days'));

        $isExpired = $this->checker->isPasswordExpired($user, 'customField', 90);

        self::assertTrue($isExpired);
    }

    public function testShortLifetime(): void
    {
        $user = new User();
        $user->setPasswordUpdatedAt(new \DateTime('-10 days'));

        $isExpired = $this->checker->isPasswordExpired($user, 'passwordUpdatedAt', 7);

        self::assertTrue($isExpired);
    }
}
