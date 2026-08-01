<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle\Console;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'database:migrate',
    description: 'Run database migrations using the Eloquent query builder.'
)]
class MigrateCommand extends Command
{
    private Capsule $capsule;
    protected string $migrationsPath;
    private string $migrationsTable;
    private string $connection;

    public function __construct(
        Capsule $capsule,
        string $migrationsPath,
        string $migrationsTable,
        string $connection
    ) {
        parent::__construct();
        $this->capsule = $capsule;
        $this->migrationsPath = $migrationsPath;
        $this->migrationsTable = $migrationsTable;
        $this->connection = $connection;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runMigrations($output, function (Migrator $migrator): array {
            return $migrator->run([$this->migrationsPath]);
        }, 'No migrations to run.', 'Migrated');
    }

    protected function runMigrations(
        OutputInterface $output,
        callable $runner,
        string $emptyMessage,
        string $actionLabel
    ): int
    {
        $resolver = $this->capsule->getDatabaseManager();
        $repository = new DatabaseMigrationRepository($resolver, $this->migrationsTable);

        if (!$repository->repositoryExists()) {
            $repository->createRepository();
            $output->writeln('<info>Created migrations table.</info>');
        }

        $migrator = new Migrator($repository, $resolver, new Filesystem());
        $migrator->setConnection($this->connection);
        $migrator->setOutput($output);
        $migrations = $runner($migrator);
        if ($migrations === []) {
            $output->writeln(sprintf('<info>%s</info>', $emptyMessage));
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>%s (%d)</info>', $actionLabel, count($migrations)));
        foreach ($migrations as $migration) {
            $name = pathinfo((string) $migration, PATHINFO_FILENAME);
            $output->writeln(sprintf('<info>- %s</info>', $name));
        }

        return Command::SUCCESS;
    }
}
