<?php

namespace GillesG\PasswordExpirationBundle\Tests\Integration;

use GillesG\PasswordExpirationBundle\EventListener\PasswordExpirationListener;
use GillesG\PasswordExpirationBundle\Service\PasswordExpirationChecker;
use GillesG\PasswordExpirationBundle\Service\PasswordHistoryChecker;
use GillesG\PasswordExpirationBundle\Tests\Integration\Stubs\Kernel as KernelStub;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

class KernelTest extends KernelTestCase
{
    protected function setUp(): void
    {
        $fs = new Filesystem();
        $fs->remove(sys_get_temp_dir().'/PasswordExpirationBundle/');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        static::ensureKernelShutdown();
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new KernelStub('test', true, $options['config'] ?? 'minimal');
    }

    public function testBundleIsLoaded(): void
    {
        self::bootKernel(['config' => 'minimal']);

        // Check that the PasswordExpirationChecker service is available
        self::assertInstanceOf(
            PasswordExpirationChecker::class,
            self::getContainer()->get(PasswordExpirationChecker::class)
        );
    }

    public function testLoadedMinimalConfig(): void
    {
        self::bootKernel(['config' => 'minimal']);

        // Check that the listener for the main firewall is registered
        $listenerId = 'password_expiration.listener.main';
        self::assertTrue(self::getContainer()->has($listenerId));
        
        $listener = self::getContainer()->get($listenerId);
        self::assertInstanceOf(PasswordExpirationListener::class, $listener);
    }

    public function testLoadedCustomConfig(): void
    {
        self::bootKernel(['config' => 'custom']);

        // Check that the listener is configured with custom settings
        $listenerId = 'password_expiration.listener.main';
        self::assertTrue(self::getContainer()->has($listenerId));
        
        $listener = self::getContainer()->get($listenerId);
        self::assertInstanceOf(PasswordExpirationListener::class, $listener);
    }

    public function testPasswordExpirationCheckerService(): void
    {
        self::bootKernel(['config' => 'minimal']);

        $checker = self::getContainer()->get(PasswordExpirationChecker::class);
        self::assertInstanceOf(PasswordExpirationChecker::class, $checker);
    }

    public function testPasswordHistoryCheckerService(): void
    {
        self::bootKernel(['config' => 'minimal']);

        $checker = self::getContainer()->get(PasswordHistoryChecker::class);
        self::assertInstanceOf(PasswordHistoryChecker::class, $checker);
    }
}
