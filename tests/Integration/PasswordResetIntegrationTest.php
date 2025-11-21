<?php

namespace GillesG\PasswordExpirationBundle\Tests\Integration;

use GillesG\PasswordExpirationBundle\Service\PasswordResetManager;
use GillesG\PasswordExpirationBundle\Service\PasswordResetService;
use GillesG\PasswordExpirationBundle\Tests\Integration\Stubs\PasswordResetKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PasswordResetIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return PasswordResetKernel::class;
    }

    protected static function createKernel(array $options = []): PasswordResetKernel
    {
        return new PasswordResetKernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true,
            $options['config'] ?? 'password_reset'
        );
    }

    public function testPasswordResetServicesAreRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertTrue($container->has(PasswordResetManager::class));
        $this->assertTrue($container->has(PasswordResetService::class));

        $resetManager = $container->get(PasswordResetManager::class);
        $resetService = $container->get(PasswordResetService::class);

        $this->assertInstanceOf(PasswordResetManager::class, $resetManager);
        $this->assertInstanceOf(PasswordResetService::class, $resetService);
    }

    public function testPasswordResetManagerCanGenerateTokens(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $resetManager = $container->get(PasswordResetManager::class);

        $user = new \stdClass();
        $user->email = 'test@example.com';

        $token = $resetManager->generateToken($user);

        $this->assertNotNull($token);
        $this->assertNotEmpty($token->getSelector());
        $this->assertNotEmpty($token->getToken());
        $this->assertSame($user, $token->getUser());
    }

    public function testPasswordResetManagerCanValidateTokens(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $resetManager = $container->get(PasswordResetManager::class);

        $user = new \stdClass();
        $user->email = 'test@example.com';

        $token = $resetManager->generateToken($user);
        $tokenString = $resetManager->getTokenString($token);
        $parts = $resetManager->parseTokenString($tokenString);

        $validatedToken = $resetManager->validateToken($parts['selector'], $parts['verifier']);

        $this->assertNotNull($validatedToken);
        $this->assertSame($user, $validatedToken->getUser());
    }
}
