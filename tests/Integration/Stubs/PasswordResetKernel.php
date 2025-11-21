<?php

namespace GillesG\PasswordExpirationBundle\Tests\Integration\Stubs;

use GillesG\PasswordExpirationBundle\PasswordExpirationBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class PasswordResetKernel extends BaseKernel
{
    private string $config;

    public function __construct(string $environment, bool $debug, string $config = 'password_reset')
    {
        parent::__construct($environment, $debug);
        $this->config = $config;
    }

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new SecurityBundle(),
            new TwigBundle(),
            new PasswordExpirationBundle(),
        ];
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/PasswordExpirationBundle/cache_reset';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/PasswordExpirationBundle/logs_reset';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(\sprintf(__DIR__.'/../config/%s_config.yaml', $this->config));
        
        // Register test services
        $loader->load(function ($container) {
            $container->register('test_user_provider', TestUserProvider::class)
                ->setPublic(true);
        });
    }
}
