<?php
/**
 * Bundle configuration.
 * @category     Bundle configuration
 * @author       Damian Szczerbiński <dszczer@gmail.com>
 */
namespace Dszczer\ListerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 * @package Dszczer\ListerBundle\DependencyInjection
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('lister');

        $rootNode->children()
            ->scalarNode('perpage')->end()
            ->scalarNode('form_name_prefix')->end()
            ->scalarNode('use_csrf')->end();

        return $treeBuilder;
    }
}