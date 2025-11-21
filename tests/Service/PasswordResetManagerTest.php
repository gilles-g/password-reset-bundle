<?php

namespace GillesG\PasswordExpirationBundle\Tests\Service;

use GillesG\PasswordExpirationBundle\Service\PasswordResetManager;
use PHPUnit\Framework\TestCase;

class PasswordResetManagerTest extends TestCase
{
    private PasswordResetManager $manager;

    protected function setUp(): void
    {
        $this->manager = new PasswordResetManager(3600);
    }

    public function testGenerateToken(): void
    {
        $user = new \stdClass();
        $user->email = 'test@example.com';

        $token = $this->manager->generateToken($user);

        $this->assertNotEmpty($token->getSelector());
        $this->assertNotEmpty($token->getToken());
        $this->assertSame($user, $token->getUser());
        $this->assertGreaterThan(new \DateTime(), $token->getExpiresAt());
        $this->assertFalse($token->isExpired());
    }

    public function testValidateToken(): void
    {
        $user = new \stdClass();
        $user->email = 'test@example.com';

        $token = $this->manager->generateToken($user);
        $selector = $token->getSelector();
        $verifier = $token->getToken();

        $validatedToken = $this->manager->validateToken($selector, $verifier);

        $this->assertNotNull($validatedToken);
        $this->assertSame($user, $validatedToken->getUser());
        $this->assertSame($selector, $validatedToken->getSelector());
    }

    public function testValidateTokenWithInvalidSelector(): void
    {
        $result = $this->manager->validateToken('invalid-selector', 'invalid-verifier');

        $this->assertNull($result);
    }

    public function testValidateTokenWithInvalidVerifier(): void
    {
        $user = new \stdClass();
        $user->email = 'test@example.com';

        $token = $this->manager->generateToken($user);
        $selector = $token->getSelector();

        $result = $this->manager->validateToken($selector, 'invalid-verifier');

        $this->assertNull($result);
    }

    public function testInvalidateToken(): void
    {
        $user = new \stdClass();
        $user->email = 'test@example.com';

        $token = $this->manager->generateToken($user);
        $selector = $token->getSelector();
        $verifier = $token->getToken();

        $this->manager->invalidateToken($selector);

        $result = $this->manager->validateToken($selector, $verifier);
        $this->assertNull($result);
    }

    public function testGetTokenString(): void
    {
        $user = new \stdClass();
        $user->email = 'test@example.com';

        $token = $this->manager->generateToken($user);
        $tokenString = $this->manager->getTokenString($token);

        $this->assertStringContainsString(':', $tokenString);
        $parts = explode(':', $tokenString);
        $this->assertCount(2, $parts);
        $this->assertSame($token->getSelector(), $parts[0]);
        $this->assertSame($token->getToken(), $parts[1]);
    }

    public function testParseTokenString(): void
    {
        $tokenString = 'selector123:verifier456';

        $result = $this->manager->parseTokenString($tokenString);

        $this->assertNotNull($result);
        $this->assertSame('selector123', $result['selector']);
        $this->assertSame('verifier456', $result['verifier']);
    }

    public function testParseInvalidTokenString(): void
    {
        $result = $this->manager->parseTokenString('invalid-token-without-separator');

        $this->assertNull($result);
    }

    public function testTokenExpiration(): void
    {
        // Create a manager with 1 second token lifetime
        $shortManager = new PasswordResetManager(1);

        $user = new \stdClass();
        $user->email = 'test@example.com';

        $token = $shortManager->generateToken($user);
        $selector = $token->getSelector();
        $verifier = $token->getToken();

        // Wait for token to expire
        sleep(2);

        $result = $shortManager->validateToken($selector, $verifier);
        $this->assertNull($result);
    }
}
