<?php
/**
 * Bundle configuration.
 * @category Bundle configuration
 * @author   Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 * @package Dszczer\ListerBundle\DependencyInjection
 * @since 0.9
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('lister')->canBeUnset();

        $rootNode->children()
            ->scalarNode('orm')
            ->cannotBeEmpty()
            ->defaultValue('auto')
            ->end()
            ->scalarNode('perpage')
            ->cannotBeEmpty()
            ->defaultValue(25)
            ->end()
            ->scalarNode('form_name_prefix')
            ->cannotBeEmpty()
            ->defaultValue('lister_filters')
            ->end()
            ->scalarNode('use_csrf')
            ->defaultTrue()
            ->end();

        return $treeBuilder;
    }
}