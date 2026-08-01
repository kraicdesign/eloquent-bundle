<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle\Console;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'database:migrate:rollback',
    description: 'Rollback the last database migration batch.'
)]
final class MigrateRollbackCommand extends MigrateCommand
{
    public function __construct(
        Capsule $capsule,
        string $migrationsPath,
        string $migrationsTable,
        string $connection
    ) {
        parent::__construct($capsule, $migrationsPath, $migrationsTable, $connection);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runMigrations($output, function (Migrator $migrator): array {
            return $migrator->rollback([$this->migrationsPath]);
        }, 'No migrations to rollback.', 'Rolled back');
    }
}
