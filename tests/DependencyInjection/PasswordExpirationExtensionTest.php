<?php

namespace GillesG\PasswordExpirationBundle\Tests\DependencyInjection;

use GillesG\PasswordExpirationBundle\DependencyInjection\PasswordExpirationExtension;
use GillesG\PasswordExpirationBundle\PasswordExpirationBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class PasswordExpirationExtensionTest extends TestCase
{
    public function testExtensionLoadsServices(): void
    {
        $container = $this->createContainer([
            'framework' => [
                'secret' => 'testing',
                'router' => [
                    'resource' => 'kernel::loadRoutes',
                    'type' => 'service',
                ],
            ],
            'security' => [
                'providers' => [
                    'test_provider' => [
                        'memory' => ['users' => []],
                    ],
                ],
                'firewalls' => [
                    'main' => [
                        'provider' => 'test_provider',
                        'password_expiration' => [
                            'lifetime_days' => 90,
                            'redirect_route' => 'password_change',
                            'user_field' => 'passwordUpdatedAt',
                            'excluded_routes' => ['password_change', 'logout'],
                        ],
                    ],
                ],
            ],
        ]);

        $container->compile();

        // Verify that the listener service is registered
        self::assertTrue($container->has('password_expiration.listener.main'));
    }

    public function testExtensionWithCustomConfiguration(): void
    {
        $container = $this->createContainer([
            'framework' => [
                'secret' => 'testing',
                'router' => [
                    'resource' => 'kernel::loadRoutes',
                    'type' => 'service',
                ],
            ],
            'security' => [
                'providers' => [
                    'test_provider' => [
                        'memory' => ['users' => []],
                    ],
                ],
                'firewalls' => [
                    'admin' => [
                        'provider' => 'test_provider',
                        'password_expiration' => [
                            'lifetime_days' => 30,
                            'redirect_route' => 'admin_password_change',
                            'user_field' => 'customPasswordField',
                            'excluded_routes' => ['admin_password_change'],
                        ],
                    ],
                ],
            ],
        ]);

        $container->compile();

        // Verify that the listener service is registered for the admin firewall
        self::assertTrue($container->has('password_expiration.listener.admin'));
    }

    private function createContainer(array $configs = []): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.bundles_metadata' => [],
            'kernel.cache_dir' => __DIR__,
            'kernel.debug' => false,
            'kernel.environment' => 'test',
            'kernel.name' => 'kernel',
            'kernel.root_dir' => __DIR__,
            'kernel.project_dir' => __DIR__,
            'kernel.container_class' => 'TestContainer',
            'kernel.charset' => 'utf8',
            'kernel.runtime_environment' => 'test',
            'kernel.build_dir' => __DIR__,
            'debug.file_link_format' => null,
            'kernel.bundles' => [
                'FrameworkBundle' => FrameworkBundle::class,
                'SecurityBundle' => SecurityBundle::class,
                'PasswordExpirationBundle' => PasswordExpirationBundle::class,
            ],
        ]));

        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension(new SecurityExtension());
        $container->registerExtension(new PasswordExpirationExtension());

        $bundle = new PasswordExpirationBundle();
        $bundle->build($container);

        foreach ($configs as $extension => $config) {
            $container->loadFromExtension($extension, $config);
        }

        return $container;
    }
}
