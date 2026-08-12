<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle\Adapter;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Kraicdesign\EloquentBundle\Contract\DatabaseConnection;
use Kraicdesign\EloquentBundle\Contract\SchemaInspector;
use PDO;

final readonly class DatabaseConnectionAdapter implements DatabaseConnection
{
    public function __construct(private Connection $connection)
    {
    }

    public function raw($value): Expression
    {
        return $this->connection->raw($value);
    }

    public function table($table, $as = null): Builder
    {
        return $this->connection->table($table, $as);
    }

    public function transaction(Closure $callback, $attempts = 1): mixed
    {
        return $this->connection->transaction($callback, $attempts);
    }

    public function getSchemaBuilder(): SchemaInspector
    {
        return new SchemaInspectorAdapter($this->connection->getSchemaBuilder());
    }

    public function update($query, $bindings = []): int
    {
        return $this->connection->update($query, $bindings);
    }

    public function statement($query, $bindings = []): bool
    {
        return $this->connection->statement($query, $bindings);
    }

    public function selectOne($query, $bindings = [], $useReadPdo = true): mixed
    {
        return $this->connection->selectOne($query, $bindings, $useReadPdo);
    }

    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        return $this->connection->select($query, $bindings, $useReadPdo);
    }

    public function getPdo(): PDO
    {
        return $this->connection->getPdo();
    }

    public function reconnect(): mixed
    {
        return $this->connection->reconnect();
    }
}
