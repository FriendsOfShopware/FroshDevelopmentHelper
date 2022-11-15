<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Component\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('frosh_development_helper');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('twig')
                    ->children()
                        ->arrayNode('exclude_keywords')
                        ->scalarPrototype()
                ->end()
        ;

        return $treeBuilder;
    }
}