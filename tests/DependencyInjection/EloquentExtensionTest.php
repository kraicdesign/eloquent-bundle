<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle\Tests\DependencyInjection;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Kraicdesign\EloquentBundle\Adapter\DatabaseConnectionAdapter;
use Kraicdesign\EloquentBundle\Adapter\SchemaInspectorAdapter;
use Kraicdesign\EloquentBundle\Contract\DatabaseConnection;
use Kraicdesign\EloquentBundle\Contract\SchemaInspector;
use Kraicdesign\EloquentBundle\DependencyInjection\EloquentExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(EloquentExtension::class)]
final class EloquentExtensionTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new EloquentExtension())->load([$config + [
            'connections' => ['default' => ['driver' => 'sqlite', 'database' => ':memory:']],
        ]], $container);

        return $container;
    }

    public function testRegistersCapsuleAndConnectionServices(): void
    {
        $container = $this->load();

        self::assertTrue($container->hasDefinition('eloquent.capsule'));
        self::assertTrue($container->hasDefinition('eloquent.connection'));
    }

    public function testRegistersTypeHintableAliases(): void
    {
        $container = $this->load();

        self::assertSame('eloquent.capsule', (string) $container->getAlias(Capsule::class));
        self::assertSame('eloquent.connection', (string) $container->getAlias(ConnectionInterface::class));
        self::assertSame('eloquent.connection', (string) $container->getAlias(Connection::class));
        self::assertSame('eloquent.database_connection', (string) $container->getAlias(DatabaseConnection::class));
        self::assertSame('eloquent.schema_inspector', (string) $container->getAlias(SchemaInspector::class));
        self::assertSame(DatabaseConnectionAdapter::class, $container->getDefinition('eloquent.database_connection')->getClass());
        self::assertSame(SchemaInspectorAdapter::class, $container->getDefinition('eloquent.schema_inspector')->getClass());
    }

    public function testContractAndLegacyAliasesResolve(): void
    {
        $container = $this->load();
        $container->setParameter('kernel.project_dir', __DIR__);
        foreach ([DatabaseConnection::class, SchemaInspector::class, ConnectionInterface::class, Connection::class] as $type) {
            $container->getAlias($type)->setPublic(true);
        }
        $container->compile();

        self::assertInstanceOf(DatabaseConnectionAdapter::class, $container->get(DatabaseConnection::class));
        self::assertInstanceOf(SchemaInspectorAdapter::class, $container->get(SchemaInspector::class));
        self::assertInstanceOf(Connection::class, $container->get(ConnectionInterface::class));
        self::assertSame($container->get(ConnectionInterface::class), $container->get(Connection::class));
    }

    public function testRegistersMigrationCommandsAsConsoleCommands(): void
    {
        $container = $this->load();

        foreach (['eloquent.command.migrate', 'eloquent.command.migrate_rollback'] as $id) {
            self::assertTrue($container->hasDefinition($id), $id . ' is not registered');
            self::assertArrayHasKey(
                'console.command',
                $container->getDefinition($id)->getTags(),
                $id . ' is not tagged console.command'
            );
        }
    }

    public function testMigrationsConnectionDefaultResolvesToDefaultConnection(): void
    {
        $container = $this->load([
            'default_connection' => 'primary',
            'connections' => ['primary' => ['driver' => 'sqlite', 'database' => ':memory:']],
            'migrations' => ['connection' => 'default'],
        ]);

        // The literal string "default" must resolve to the configured default
        // connection name, not be passed through as a connection called "default".
        self::assertSame('primary', $container->getParameter('eloquent.migrations.connection'));
    }

    public function testExplicitMigrationsConnectionIsNotRewritten(): void
    {
        $container = $this->load([
            'default_connection' => 'primary',
            'connections' => [
                'primary' => ['driver' => 'sqlite', 'database' => ':memory:'],
                'ddl' => ['driver' => 'sqlite', 'database' => ':memory:'],
            ],
            'migrations' => ['connection' => 'ddl'],
        ]);

        self::assertSame('ddl', $container->getParameter('eloquent.migrations.connection'));
    }

    public function testExposesMigrationsPathAndTableParameters(): void
    {
        $container = $this->load([
            'migrations' => ['path' => '/srv/migrations', 'table' => 'schema_versions'],
        ]);

        self::assertSame('/srv/migrations', $container->getParameter('eloquent.migrations.path'));
        self::assertSame('schema_versions', $container->getParameter('eloquent.migrations.table'));
    }
}
