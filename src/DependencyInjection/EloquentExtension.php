<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle\DependencyInjection;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Kraicdesign\EloquentBundle\Adapter\DatabaseConnectionAdapter;
use Kraicdesign\EloquentBundle\Adapter\SchemaInspectorAdapter;
use Kraicdesign\EloquentBundle\CapsuleFactory;
use Kraicdesign\EloquentBundle\Console\MigrateCommand;
use Kraicdesign\EloquentBundle\Console\MigrateRollbackCommand;
use Kraicdesign\EloquentBundle\Contract\DatabaseConnection;
use Kraicdesign\EloquentBundle\Contract\SchemaInspector;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class EloquentExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setDefinition('eloquent.capsule', new Definition(Capsule::class))
            ->setFactory([CapsuleFactory::class, 'create'])
            ->setArguments([$config['connections'], $config['default_connection']])
            ->setPublic(false);

        $container->setAlias(Capsule::class, 'eloquent.capsule')->setPublic(false);

        $container->setDefinition('eloquent.connection', new Definition(ConnectionInterface::class))
            ->setFactory([new Reference('eloquent.capsule'), 'getConnection'])
            ->setArguments([$config['default_connection']])
            ->setPublic(false);

        $container->setAlias(ConnectionInterface::class, 'eloquent.connection')->setPublic(false);
        $container->setAlias(Connection::class, 'eloquent.connection')->setPublic(false);

        $container->setDefinition('eloquent.database_connection', new Definition(DatabaseConnectionAdapter::class))
            ->setArguments([new Reference('eloquent.connection')])
            ->setPublic(false);
        $container->setAlias(DatabaseConnection::class, 'eloquent.database_connection')->setPublic(false);

        $container->setDefinition('eloquent.schema_builder', new Definition(SchemaBuilder::class))
            ->setFactory([new Reference('eloquent.connection'), 'getSchemaBuilder'])
            ->setPublic(false);
        $container->setDefinition('eloquent.schema_inspector', new Definition(SchemaInspectorAdapter::class))
            ->setArguments([new Reference('eloquent.schema_builder')])
            ->setPublic(false);
        $container->setAlias(SchemaInspector::class, 'eloquent.schema_inspector')->setPublic(false);

        $migrationsConnection = $config['migrations']['connection'] === 'default'
            ? $config['default_connection']
            : $config['migrations']['connection'];

        $container->setParameter('eloquent.migrations.path', $config['migrations']['path']);
        $container->setParameter('eloquent.migrations.table', $config['migrations']['table']);
        $container->setParameter('eloquent.migrations.connection', $migrationsConnection);

        $container->setDefinition('eloquent.command.migrate', new Definition(MigrateCommand::class))
            ->setArguments([
                new Reference('eloquent.capsule'),
                '%eloquent.migrations.path%',
                '%eloquent.migrations.table%',
                '%eloquent.migrations.connection%',
            ])
            ->addTag('console.command')
            ->setPublic(false);

        $container->setDefinition('eloquent.command.migrate_rollback', new Definition(MigrateRollbackCommand::class))
            ->setArguments([
                new Reference('eloquent.capsule'),
                '%eloquent.migrations.path%',
                '%eloquent.migrations.table%',
                '%eloquent.migrations.connection%',
            ])
            ->addTag('console.command')
            ->setPublic(false);
    }
}
