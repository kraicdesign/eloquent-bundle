<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle\Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Kraicdesign\EloquentBundle\CapsuleFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[CoversClass(CapsuleFactory::class)]
#[RequiresPhpExtension('pdo_sqlite')]
final class CapsuleFactoryTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private function connections(): array
    {
        return [
            'primary' => ['driver' => 'sqlite', 'database' => ':memory:'],
            'replica' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ];
    }

    public function testRegistersEveryConfiguredConnection(): void
    {
        $capsule = CapsuleFactory::create($this->connections(), 'primary');

        self::assertInstanceOf(Capsule::class, $capsule);
        self::assertNotNull($capsule->getConnection('primary'));
        self::assertNotNull($capsule->getConnection('replica'));
    }

    public function testSetsTheRequestedDefaultConnection(): void
    {
        $capsule = CapsuleFactory::create($this->connections(), 'replica');

        self::assertSame(
            'replica',
            $capsule->getDatabaseManager()->getDefaultConnection()
        );
    }

    public function testProducesAUsableConnection(): void
    {
        $capsule = CapsuleFactory::create($this->connections(), 'primary');
        $connection = $capsule->getConnection('primary');

        $connection->statement('CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT)');
        $connection->table('widgets')->insert(['id' => 1, 'name' => 'sprocket']);

        self::assertSame('sprocket', $connection->table('widgets')->value('name'));
    }

    /**
     * setAsGlobal() and bootEloquent() are called by the factory. This is
     * deliberate -- it is what lets Eloquent models resolve a connection without
     * being handed one -- and it is documented as a constraint in the README.
     */
    public function testMakesTheCapsuleGloballyResolvable(): void
    {
        CapsuleFactory::create($this->connections(), 'primary');

        self::assertNotNull(Capsule::connection('primary'));
    }
}
