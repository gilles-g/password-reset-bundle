<?php

namespace GillesG\PasswordExpirationBundle\Tests\Service;

use GillesG\PasswordExpirationBundle\Service\PasswordHistoryChecker;
use GillesG\PasswordExpirationBundle\Tests\Integration\Stubs\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;

class PasswordHistoryCheckerTest extends TestCase
{
    private PasswordHistoryChecker $checker;
    private UserPasswordHasher $passwordHasher;

    protected function setUp(): void
    {
        // Create a password hasher factory for testing
        $factory = new PasswordHasherFactory([
            User::class => ['algorithm' => 'auto']
        ]);
        
        $this->passwordHasher = new UserPasswordHasher($factory);
        $this->checker = new PasswordHistoryChecker($factory);
    }

    public function testPasswordNotReusedWithEmptyHistory(): void
    {
        $user = new User();
        $user->setPasswordHistory([]);

        $isReused = $this->checker->isPasswordReused($user, 'newPassword123');

        self::assertFalse($isReused);
    }

    public function testPasswordReusedWithMatchingHistory(): void
    {
        $user = new User();
        $password = 'testPassword123';
        
        // Hash the password and add it to history
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPasswordHistory([$hashedPassword]);

        // Check if the same password is reused
        $isReused = $this->checker->isPasswordReused($user, $password);

        self::assertTrue($isReused);
    }

    public function testPasswordNotReusedWithDifferentPassword(): void
    {
        $user = new User();
        $oldPassword = 'oldPassword123';
        $newPassword = 'newPassword456';
        
        $hashedOldPassword = $this->passwordHasher->hashPassword($user, $oldPassword);
        $user->setPasswordHistory([$hashedOldPassword]);

        $isReused = $this->checker->isPasswordReused($user, $newPassword);

        self::assertFalse($isReused);
    }

    public function testPasswordReusedWithMultipleHistoryEntries(): void
    {
        $user = new User();
        $password1 = 'password1';
        $password2 = 'password2';
        $password3 = 'password3';
        
        $hash1 = $this->passwordHasher->hashPassword($user, $password1);
        $hash2 = $this->passwordHasher->hashPassword($user, $password2);
        $hash3 = $this->passwordHasher->hashPassword($user, $password3);
        
        $user->setPasswordHistory([$hash3, $hash2, $hash1]);

        // Check that each password is detected as reused
        self::assertTrue($this->checker->isPasswordReused($user, $password1));
        self::assertTrue($this->checker->isPasswordReused($user, $password2));
        self::assertTrue($this->checker->isPasswordReused($user, $password3));
        
        // Check that a new password is not detected as reused
        self::assertFalse($this->checker->isPasswordReused($user, 'newPassword'));
    }

    public function testUserWithoutPasswordHistoryField(): void
    {
        $user = new class {
            public function getId(): int
            {
                return 1;
            }
        };

        $isReused = $this->checker->isPasswordReused($user, 'anyPassword');

        self::assertFalse($isReused);
    }

    public function testCreatePasswordFingerprint(): void
    {
        $user = new User();
        $password = 'testPassword123';

        $fingerprint = $this->checker->createPasswordFingerprint($user, $password);

        // Verify the fingerprint is a valid hash
        self::assertNotEmpty($fingerprint);
        self::assertNotEquals($password, $fingerprint);
        
        // Verify the fingerprint can be used to verify the password
        $user->setPassword($fingerprint);
        self::assertTrue($this->passwordHasher->isPasswordValid($user, $password));
    }

    public function testAddPasswordToHistory(): void
    {
        $user = new User();
        $password1 = 'password1';
        $password2 = 'password2';
        
        $hash1 = $this->passwordHasher->hashPassword($user, $password1);
        $hash2 = $this->passwordHasher->hashPassword($user, $password2);

        // Add first password
        $this->checker->addPasswordToHistory($user, $hash1);
        self::assertCount(1, $user->getPasswordHistory());
        self::assertEquals($hash1, $user->getPasswordHistory()[0]);

        // Add second password
        $this->checker->addPasswordToHistory($user, $hash2);
        self::assertCount(2, $user->getPasswordHistory());
        self::assertEquals($hash2, $user->getPasswordHistory()[0]); // Most recent first
        self::assertEquals($hash1, $user->getPasswordHistory()[1]);
    }

    public function testAddPasswordToHistoryWithMaxLimit(): void
    {
        $user = new User();
        $maxHistory = 3;
        
        $hashes = [];
        for ($i = 1; $i <= 5; $i++) {
            $hash = $this->passwordHasher->hashPassword($user, "password{$i}");
            $hashes[] = $hash;
            $this->checker->addPasswordToHistory($user, $hash, 'passwordHistory', $maxHistory);
        }

        // Should only keep the last 3 passwords
        self::assertCount($maxHistory, $user->getPasswordHistory());
        
        // Verify the most recent passwords are kept
        self::assertEquals($hashes[4], $user->getPasswordHistory()[0]); // password5
        self::assertEquals($hashes[3], $user->getPasswordHistory()[1]); // password4
        self::assertEquals($hashes[2], $user->getPasswordHistory()[2]); // password3
    }

    public function testAddPasswordToHistoryWithDefaultMaxLimit(): void
    {
        $user = new User();
        
        // Add 6 passwords with default limit of 5
        for ($i = 1; $i <= 6; $i++) {
            $hash = $this->passwordHasher->hashPassword($user, "password{$i}");
            $this->checker->addPasswordToHistory($user, $hash);
        }

        // Should only keep the last 5 passwords (default)
        self::assertCount(5, $user->getPasswordHistory());
    }

    public function testAddPasswordToHistoryUserWithoutField(): void
    {
        $user = new class {
            public function getId(): int
            {
                return 1;
            }
        };

        // Should not throw an exception
        $this->checker->addPasswordToHistory($user, 'someHash');
        
        // Just verify it doesn't crash
        self::assertTrue(true);
    }

    public function testCustomHistoryFieldName(): void
    {
        $user = new class {
            private array $customPasswordHistory = [];

            public function getCustomPasswordHistory(): array
            {
                return $this->customPasswordHistory;
            }

            public function setCustomPasswordHistory(array $history): self
            {
                $this->customPasswordHistory = $history;
                return $this;
            }
        };

        $password = 'testPassword';
        $hash = $this->passwordHasher->hashPassword(new User(), $password);
        
        $user->setCustomPasswordHistory([$hash]);

        // Note: This would require a modified User object with the proper interface
        // For now, we'll just test that custom field names work with the add method
        $this->checker->addPasswordToHistory($user, $hash, 'customPasswordHistory');
        
        self::assertCount(2, $user->getCustomPasswordHistory());
    }
}
