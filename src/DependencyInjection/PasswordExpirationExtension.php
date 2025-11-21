<?php

namespace GillesG\PasswordExpirationBundle\DependencyInjection;

use GillesG\PasswordExpirationBundle\Service\PasswordResetManager;
use GillesG\PasswordExpirationBundle\Service\PasswordResetService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class PasswordExpirationExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        // Configure password reset services if enabled
        if (isset($config['password_reset']) && $config['password_reset']['enabled']) {
            $this->registerPasswordResetServices($container, $config['password_reset']);
        }
    }

    private function registerPasswordResetServices(ContainerBuilder $container, array $config): void
    {
        // Validate from_email is configured
        if (empty($config['email']['from_email'])) {
            throw new \InvalidArgumentException(
                'The "password_expiration.password_reset.email.from_email" configuration option must be set when password reset is enabled.'
            );
        }

        // Register PasswordResetManager
        $managerDefinition = new Definition(PasswordResetManager::class);
        $managerDefinition->setArguments([
            $config['token_lifetime']
        ]);
        $managerDefinition->setPublic(true);
        $container->setDefinition(PasswordResetManager::class, $managerDefinition);

        // Register PasswordResetService
        $serviceDefinition = new Definition(PasswordResetService::class);
        $serviceDefinition->setArguments([
            new Reference(PasswordResetManager::class),
            new Reference('mailer'),
            new Reference('twig'),
            $config['email']['from_email'],
            $config['email']['from_name'],
            $config['email']['html_template'],
            $config['email']['text_template'],
            $config['email']['subject']
        ]);
        $serviceDefinition->setPublic(true);
        $container->setDefinition(PasswordResetService::class, $serviceDefinition);
    }

    public function getAlias(): string
    {
        return 'password_expiration';
    }
}
