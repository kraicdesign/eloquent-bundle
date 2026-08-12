<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle\Contract;

interface SchemaInspector
{
    public function hasTable(string $table): bool;

    public function hasColumn(string $table, string $column): bool;
}
