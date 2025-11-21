<?php

namespace GillesG\PasswordExpirationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('password_expiration');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('password_reset')
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('token_lifetime')
                            ->info('Token lifetime in seconds')
                            ->defaultValue(3600)
                            ->min(60)
                        ->end()
                        ->arrayNode('email')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('from_email')
                                    ->info('Email address to send reset emails from')
                                    ->defaultNull()
                                ->end()
                                ->scalarNode('from_name')
                                    ->info('Name to send reset emails from')
                                    ->defaultValue('Password Reset')
                                ->end()
                                ->scalarNode('subject')
                                    ->info('Email subject')
                                    ->defaultValue('Password Reset Request')
                                ->end()
                                ->scalarNode('html_template')
                                    ->info('Path to HTML email template')
                                    ->defaultValue('@PasswordExpiration/emails/password_reset.html.twig')
                                ->end()
                                ->scalarNode('text_template')
                                    ->info('Path to text email template')
                                    ->defaultValue('@PasswordExpiration/emails/password_reset.txt.twig')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
