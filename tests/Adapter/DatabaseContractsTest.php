<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle\Tests\Adapter;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Kraicdesign\EloquentBundle\Adapter\DatabaseConnectionAdapter;
use Kraicdesign\EloquentBundle\Adapter\SchemaInspectorAdapter;
use Kraicdesign\EloquentBundle\Contract\DatabaseConnection;
use Kraicdesign\EloquentBundle\Contract\SchemaInspector;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

#[CoversClass(DatabaseConnectionAdapter::class)]
#[CoversClass(SchemaInspectorAdapter::class)]
#[CoversClass(DatabaseConnection::class)]
#[CoversClass(SchemaInspector::class)]
final class DatabaseContractsTest extends TestCase
{
    public function testContractsExposeOnlyTheIntendedMethods(): void
    {
        $connectionMethods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(DatabaseConnection::class))->getMethods(),
        );
        $schemaMethods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(SchemaInspector::class))->getMethods(),
        );

        self::assertEqualsCanonicalizing([
            'raw',
            'table',
            'transaction',
            'getSchemaBuilder',
            'update',
            'statement',
            'selectOne',
            'select',
            'getPdo',
            'reconnect',
        ], $connectionMethods);
        self::assertEqualsCanonicalizing(['hasTable', 'hasColumn'], $schemaMethods);
    }

    public function testConnectionMethodsDelegateArgumentsAndResultsUnchanged(): void
    {
        $queryBuilder = $this->createStub(QueryBuilder::class);
        $expression = new Expression('CURRENT_TIMESTAMP');
        $pdo = new PDO('sqlite::memory:');
        $selected = (object) ['id' => 7];
        $rows = [$selected];
        $transactionResult = new \stdClass();
        $callback = static fn (): object => $transactionResult;

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('raw')
            ->with('CURRENT_TIMESTAMP')->willReturn($expression);
        $connection->expects(self::once())->method('table')
            ->with('users', 'u')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('transaction')
            ->with($callback, 3)->willReturn($transactionResult);
        $connection->expects(self::once())->method('update')
            ->with('update users set active = ?', [true])->willReturn(4);
        $connection->expects(self::once())->method('statement')
            ->with('delete from users where active = ?', [false])->willReturn(true);
        $connection->expects(self::once())->method('selectOne')
            ->with('select * from users where id = ?', [7], false)->willReturn($selected);
        $connection->expects(self::once())->method('select')
            ->with('select * from users', [], false)->willReturn($rows);
        $connection->expects(self::once())->method('getPdo')->willReturn($pdo);
        $connection->expects(self::once())->method('reconnect')->willReturn(false);

        $adapter = new DatabaseConnectionAdapter($connection);

        self::assertSame($expression, $adapter->raw('CURRENT_TIMESTAMP'));
        self::assertSame($queryBuilder, $adapter->table('users', 'u'));
        self::assertSame($transactionResult, $adapter->transaction($callback, 3));
        self::assertSame(4, $adapter->update('update users set active = ?', [true]));
        self::assertTrue($adapter->statement('delete from users where active = ?', [false]));
        self::assertSame($selected, $adapter->selectOne('select * from users where id = ?', [7], false));
        self::assertSame($rows, $adapter->select('select * from users', [], false));
        self::assertSame($pdo, $adapter->getPdo());
        self::assertFalse($adapter->reconnect());
    }

    public function testSchemaMethodsAndSchemaBuilderDelegate(): void
    {
        $schemaBuilder = $this->createMock(SchemaBuilder::class);
        $schemaBuilder->expects(self::once())->method('hasTable')->with('pc_tags')->willReturn(true);
        $schemaBuilder->expects(self::once())->method('hasColumn')
            ->with('pc_tags', 'show_to_all_pcs')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('getSchemaBuilder')->willReturn($schemaBuilder);

        $inspector = (new DatabaseConnectionAdapter($connection))->getSchemaBuilder();

        self::assertInstanceOf(SchemaInspector::class, $inspector);
        self::assertTrue($inspector->hasTable('pc_tags'));
        self::assertFalse($inspector->hasColumn('pc_tags', 'show_to_all_pcs'));
    }

    public function testAdapterSignaturesRemainCompatibleWithIlluminate(): void
    {
        $this->assertCompatibleMethods(DatabaseConnectionAdapter::class, Connection::class, [
            'raw',
            'table',
            'transaction',
            'getSchemaBuilder',
            'update',
            'statement',
            'selectOne',
            'select',
            'getPdo',
            'reconnect',
        ]);
        $this->assertCompatibleMethods(SchemaInspectorAdapter::class, SchemaBuilder::class, [
            'hasTable',
            'hasColumn',
        ]);
    }

    public function testSchemaInspectionWorksAgainstSQLite(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection = $capsule->getConnection();
        $connection->statement('create table pc_tags (id integer primary key, show_to_all_pcs integer)');

        $inspector = (new DatabaseConnectionAdapter($connection))->getSchemaBuilder();

        self::assertInstanceOf(SchemaInspector::class, $inspector);
        self::assertTrue($inspector->hasTable('pc_tags'));
        self::assertTrue($inspector->hasColumn('pc_tags', 'show_to_all_pcs'));
        self::assertFalse($inspector->hasColumn('pc_tags', 'missing'));
    }

    public function testGetPdoReturnsNullAfterDisconnectWithoutReconnecting(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection = $capsule->getConnection();
        $adapter = new DatabaseConnectionAdapter($connection);

        self::assertInstanceOf(PDO::class, $adapter->getPdo());

        $connection->disconnect();

        self::assertNull($adapter->getPdo());
    }

    /**
     * @param class-string $adapterClass
     * @param class-string $delegateClass
     * @param list<string> $methods
     */
    private function assertCompatibleMethods(string $adapterClass, string $delegateClass, array $methods): void
    {
        foreach ($methods as $method) {
            $adapter = new ReflectionMethod($adapterClass, $method);
            $delegate = new ReflectionMethod($delegateClass, $method);
            $message = sprintf('%s::%s', $adapterClass, $method);

            self::assertSame($delegate->getNumberOfRequiredParameters(), $adapter->getNumberOfRequiredParameters(), $message);
            self::assertLessThanOrEqual($delegate->getNumberOfParameters(), $adapter->getNumberOfParameters(), $message);

            $delegateParameters = $delegate->getParameters();
            foreach ($adapter->getParameters() as $position => $parameter) {
                $delegateParameter = $delegateParameters[$position];
                self::assertSame($delegateParameter->getName(), $parameter->getName(), $message);
                self::assertSame($delegateParameter->isPassedByReference(), $parameter->isPassedByReference(), $message);
                self::assertSame($delegateParameter->isVariadic(), $parameter->isVariadic(), $message);
                self::assertSame($this->typeName($delegateParameter->getType()), $this->typeName($parameter->getType()), $message);
                $this->assertSameDefault($delegateParameter, $parameter, $message);
            }

            foreach (array_slice($delegateParameters, $adapter->getNumberOfParameters()) as $optionalParameter) {
                self::assertTrue($optionalParameter->isOptional(), $message . ' may only omit optional trailing parameters');
            }
        }
    }

    private function assertSameDefault(ReflectionParameter $delegate, ReflectionParameter $adapter, string $message): void
    {
        self::assertSame($delegate->isDefaultValueAvailable(), $adapter->isDefaultValueAvailable(), $message);

        if ($delegate->isDefaultValueAvailable()) {
            self::assertSame($delegate->getDefaultValue(), $adapter->getDefaultValue(), $message);
        }
    }

    private function typeName(?ReflectionType $type): ?string
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof ReflectionUnionType) {
            $names = array_map(static fn (ReflectionNamedType $named): string => $named->getName(), $type->getTypes());
            sort($names);

            return implode('|', $names);
        }

        return null;
    }
}
