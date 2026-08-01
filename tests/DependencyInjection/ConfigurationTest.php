<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle\Tests\DependencyInjection;

use Kraicdesign\EloquentBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    /**
     * @param array<int, array<string, mixed>> $configs
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }

    public function testAppliesDefaults(): void
    {
        $config = $this->process([[
            'connections' => ['default' => ['driver' => 'sqlite']],
        ]]);

        self::assertSame('default', $config['default_connection']);
        self::assertSame('migrations', $config['migrations']['table']);
        self::assertSame('default', $config['migrations']['connection']);
        self::assertSame(
            '%kernel.project_dir%/database/migrations',
            $config['migrations']['path']
        );
    }

    public function testRequiresAtLeastOneConnection(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['connections' => []]]);
    }

    public function testConnectionKeysArePassedThroughUntouched(): void
    {
        $config = $this->process([[
            'connections' => [
                'default' => [
                    'driver' => 'pgsql',
                    'foreign_key_constraints' => false,
                    'sslmode' => 'require',
                ],
            ],
        ]]);

        // normalizeKeys(false) means underscores survive; Illuminate options are
        // not renamed or validated by this bundle.
        self::assertSame([
            'driver' => 'pgsql',
            'foreign_key_constraints' => false,
            'sslmode' => 'require',
        ], $config['connections']['default']);
    }

    public function testSupportsMultipleNamedConnections(): void
    {
        $config = $this->process([[
            'default_connection' => 'primary',
            'connections' => [
                'primary' => ['driver' => 'pgsql'],
                'replica' => ['driver' => 'pgsql'],
            ],
        ]]);

        self::assertSame('primary', $config['default_connection']);
        self::assertSame(['primary', 'replica'], array_keys($config['connections']));
    }

    public function testOverridesAreHonoured(): void
    {
        $config = $this->process([[
            'default_connection' => 'primary',
            'connections' => ['primary' => ['driver' => 'sqlite']],
            'migrations' => [
                'path' => '/tmp/migrations',
                'table' => 'schema_versions',
                'connection' => 'primary',
            ],
        ]]);

        self::assertSame('/tmp/migrations', $config['migrations']['path']);
        self::assertSame('schema_versions', $config['migrations']['table']);
        self::assertSame('primary', $config['migrations']['connection']);
    }
}
