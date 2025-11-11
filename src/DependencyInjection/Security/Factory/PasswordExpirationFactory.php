<?php

namespace GillesG\PasswordExpirationBundle\DependencyInjection\Security\Factory;

use GillesG\PasswordExpirationBundle\EventListener\PasswordExpirationListener;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class PasswordExpirationFactory implements AuthenticatorFactoryInterface
{
    public function getPriority(): int
    {
        return -10;
    }

    public function getKey(): string
    {
        return 'password_expiration';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        /** @var ArrayNodeDefinition $node */
        $node
            ->children()
                ->integerNode('lifetime_days')
                    ->isRequired()
                    ->info('Number of days before password expiration')
                ->end()
                ->scalarNode('redirect_route')
                    ->isRequired()
                    ->info('Route to redirect when password is expired')
                ->end()
                ->scalarNode('user_field')
                    ->defaultValue('passwordUpdatedAt')
                    ->info('User entity field name that stores password update date')
                ->end()
                ->arrayNode('excluded_routes')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                    ->info('Routes that should be excluded from password expiration check')
                ->end()
            ->end()
        ;
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        $listenerId = 'password_expiration.listener.' . $firewallName;

        $container
            ->setDefinition($listenerId, new ChildDefinition('GillesG\PasswordExpirationBundle\EventListener\PasswordExpirationListener'))
            ->replaceArgument(0, $config['lifetime_days'])
            ->replaceArgument(1, $config['redirect_route'])
            ->replaceArgument(2, $config['user_field'])
            ->replaceArgument(3, $config['excluded_routes'])
            ->replaceArgument(4, $firewallName)
            ->addTag('kernel.event_listener', [
                'event' => 'kernel.request',
                'method' => 'onKernelRequest',
                'priority' => 7
            ]);

        return $listenerId;
    }
}
