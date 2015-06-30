<?php

namespace Pumukit\NotificationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('pumukit_notification');

        $rootNode
          ->children()
            ->booleanNode('enable')->isRequired()
              ->defaultFalse()
              ->info('Enable notifications to send emails')
            ->end()
            ->scalarNode('platform_name')->isRequired()
              ->defaultValue('Pumukit')
              ->info('The name of the Pumukit platform')
            ->end()
            ->scalarNode('sender_email')->isRequired()
              ->info('The email of the sender')
            ->end()
            ->scalarNode('sender_name')->isRequired()
              ->info('The name of the sender')
            ->end()
            ->booleanNode('notificate_errors_to_sender')->isRequired()
              ->defaultTrue()
              ->info('Whether the sender email receives error notifications or not')
            ->end()
          ->end()
          ;

        return $treeBuilder;
    }
}
