<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('eloquent');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('default_connection')
                    ->defaultValue('default')
                ->end()
                ->arrayNode('migrations')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('path')
                            ->defaultValue('%kernel.project_dir%/database/migrations')
                        ->end()
                        ->scalarNode('table')
                            ->defaultValue('migrations')
                        ->end()
                        ->scalarNode('connection')
                            ->defaultValue('default')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('connections')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->normalizeKeys(false)
                        ->variablePrototype()->end()
                    ->end()
                    ->requiresAtLeastOneElement()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
