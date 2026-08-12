<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle\Contract;

use Closure;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use PDO;

interface DatabaseConnection
{
    public function raw(mixed $value): Expression;

    public function table(Closure|Builder|ExpressionContract|string $table, ?string $as = null): Builder;

    /**
     * @template TReturn
     *
     * @param Closure(\Illuminate\Database\Connection): TReturn $callback
     * @return TReturn
     */
    public function transaction(Closure $callback, int $attempts = 1): mixed;

    public function getSchemaBuilder(): SchemaInspector;

    /** @param array<array-key, mixed> $bindings */
    public function update(string $query, array $bindings = []): int;

    /** @param array<array-key, mixed> $bindings */
    public function statement(string $query, array $bindings = []): bool;

    /**
     * @param array<array-key, mixed> $bindings
     * @return mixed
     */
    public function selectOne(string $query, array $bindings = [], bool $useReadPdo = true): mixed;

    /**
     * @param array<array-key, mixed> $bindings
     * @return array<array-key, mixed>
     */
    public function select(string $query, array $bindings = [], bool $useReadPdo = true): array;

    public function getPdo(): ?PDO;

    public function disconnect(): void;

    /** @return mixed|false */
    public function reconnect(): mixed;
}
