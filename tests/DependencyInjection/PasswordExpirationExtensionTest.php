<?php

namespace GillesG\PasswordExpirationBundle\Tests\DependencyInjection;

use GillesG\PasswordExpirationBundle\DependencyInjection\PasswordExpirationExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PasswordExpirationExtensionTest extends TestCase
{
    public function testExtensionLoadsServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new PasswordExpirationExtension();
        
        $extension->load([], $container);
        
        // Verify that base services are registered
        self::assertTrue($container->hasDefinition('GillesG\PasswordExpirationBundle\Service\PasswordExpirationChecker'));
        self::assertTrue($container->hasDefinition('GillesG\PasswordExpirationBundle\EventListener\PasswordExpirationListener'));
    }
    
    public function testExtensionAlias(): void
    {
        $extension = new PasswordExpirationExtension();
        
        self::assertSame('password_expiration', $extension->getAlias());
    }
}
