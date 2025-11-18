<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use GillesG\PasswordExpirationBundle\EventListener\PasswordExpirationListener;
use GillesG\PasswordExpirationBundle\Service\PasswordExpirationChecker;
use GillesG\PasswordExpirationBundle\Service\PasswordHistoryChecker;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set(PasswordExpirationChecker::class)
        ->public();

    $services->set(PasswordHistoryChecker::class)
        ->args([
            service('security.password_hasher_factory')
        ])
        ->public();

    $services->set(PasswordExpirationListener::class)
        ->args([
            null, // $lifetimeDays - will be set by factory
            null, // $redirectRoute - will be set by factory
            null, // $userField - will be set by factory
            null, // $excludedRoutes - will be set by factory
            null, // $firewallName - will be set by factory
            service('security.token_storage'),
            service('router'),
            service(PasswordExpirationChecker::class)
        ])
        ->abstract();
};
