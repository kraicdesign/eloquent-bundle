<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle\Adapter;

use Illuminate\Database\Schema\Builder;
use Kraicdesign\EloquentBundle\Contract\SchemaInspector;

final readonly class SchemaInspectorAdapter implements SchemaInspector
{
    public function __construct(private Builder $builder)
    {
    }

    public function hasTable($table): bool
    {
        return $this->builder->hasTable($table);
    }

    public function hasColumn($table, $column): bool
    {
        return $this->builder->hasColumn($table, $column);
    }
}
