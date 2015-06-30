<?php

namespace Pumukit\EncoderBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Pumukit\EncoderBundle\Services\CpuService;
use Pumukit\EncoderBundle\Services\ProfileService;

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
        $rootNode = $treeBuilder->root('pumukit_encoder');

        //Doc in http://symfony.com/doc/current/components/config/definition.html
        $this->addCpusSection($rootNode);
        $this->addProfilesSection($rootNode);
        $this->addThumbnailSection($rootNode);

        return $treeBuilder;
    }


    /**
     * Adds `profiles` section.
     *
     * @param ArrayNodeDefinition $node
     */
    public function addProfilesSection(ArrayNodeDefinition $node) 
    {
        $node
            ->children()
                ->arrayNode('profiles')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->booleanNode('display')->defaultValue(false)
                                ->info('Displays the track')->end()
                            ->booleanNode('wizard')->defaultValue(true)
                                ->info('Shown in wizard')->end()
                            ->booleanNode('master')->defaultValue(true)
                                ->info('The track is master copy')->end()
                            ->scalarNode('tags')->defaultValue('')->info('Tags used in tracks created with this profiles')->end()
                            ->scalarNode('format')->info('Format of the track')->end()
                            ->scalarNode('codec')->info('Codec of the track')->end()
                            ->scalarNode('mime_type')->info('Mime Type of the track')->end()
                            ->scalarNode('extension')->info('Extension of the track. If empty the input file extension is used.')->end()
                            ->integerNode('resolution_hor')->min(0)->defaultValue(0)
                                ->info('Horizontal resolution of the track')->end()
                            ->integerNode('resolution_ver')->min(0)->defaultValue(0)
                                ->info('Vertical resolution of the track')->end()
                            ->scalarNode('bitrate')->info('Bit rate of the track')->end()
                            ->scalarNode('framerate')->defaultValue('0')
                                ->info('Framerate of the track')->end()
                            ->integerNode('channels')->min(0)->defaultValue(1)
                                ->info('Available Channels')->end()
                            ->booleanNode('audio')->defaultValue(false)
                                ->info('The track is only audio')->end()
                            ->scalarNode('bat')->isRequired()->cannotBeEmpty()
                                ->info('Command line to execute transcodification of track')->end()
                            ->scalarNode('file_cfg')->info('Configuration file')->end()
                            ->arrayNode('streamserver')
                                ->isRequired()->cannotBeEmpty()
                                ->children()
                                    ->scalarNode('name')->isRequired()->cannotBeEmpty()
                                        ->info('Name of the streamserver')->end()
                                    ->enumNode('type')
                                        ->values(array(ProfileService::STREAMSERVER_STORE, ProfileService::STREAMSERVER_DOWNLOAD, 
                                                       ProfileService::STREAMSERVER_WMV, ProfileService::STREAMSERVER_FMS, ProfileService::STREAMSERVER_RED5))
                                        ->isRequired()
                                        ->info('Streamserver type')->end()
                                    ->scalarNode('host')->isRequired()->cannotBeEmpty()
                                        ->info('Streamserver Hostname (or IP)')->end()
                                    ->scalarNode('description')->info('Streamserver host description')->end()
                                    ->scalarNode('dir_out')->isRequired()->cannotBeEmpty()
                                        ->info('Directory path of resulting track')->end()
                                    ->scalarNode('url_out')->info('URL of resulting track')->end()
                                ->end()
                                ->info('Type of streamserver for transcodification and data')->end()
                            ->scalarNode('app')->isRequired()->cannotBeEmpty()
                                ->info('Application to execute')->end()      
                            ->integerNode('rel_duration_size')->defaultValue(1)
                                ->info('Relation between duration and size of track')->end()
                            ->integerNode('rel_duration_trans')->defaultValue(1)
                                ->info('Relation between duration and trans of track')->end()
                            ->scalarNode('prescript')->info('Pre-script to execute')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
          ;
    }


    /**
     * Adds `cpu` section.
     *
     * @param ArrayNodeDefinition $node
     */
    public function addCpusSection(ArrayNodeDefinition $node) 
    {
        $node
            ->children()
                ->arrayNode('cpus')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('host')->isRequired()->cannotBeEmpty()
                                ->info('Encoder Hostnames (or IPs)')->end()
                            ->integerNode('number')->min(0)->defaultValue(1)
                                ->info('Maximum number of concurrent encoding jobs')->end()
                            ->integerNode('max')->min(0)->defaultValue(1)
                                ->info('Top for the maximum number of concurrent encoding jobs')->end()
                            ->enumNode('type')->values(array(CpuService::TYPE_LINUX, CpuService::TYPE_WINDOWS, CpuService::TYPE_GSTREAMER))
                                ->defaultValue(CpuService::TYPE_LINUX)
                                ->info('Type of the encoder host (linux, windows or gstreamer)')->end()
                            ->scalarNode('user')
                            ->info('Specifies the user to log in as on the remote encoder host')->end()
                            ->scalarNode('password')
                            ->info('Specifies the user to log in as on the remote encoder host')->end()
                            ->scalarNode('description')->defaultValue('')
                            ->info('Encoder host description')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * Adds `thumbnail` section.
     *
     * @param ArrayNodeDefinition $node
     */
    public function addThumbnailSection(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('thumbnail')
                    ->canBeUnset()
                    ->children()
                        ->integerNode('width')->defaultValue(304)
                            ->info('Width resolution of thumbnail')->end()
                        ->integerNode('height')->defaultValue(242)
                            ->info('Height resolution of thumbnail')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
