# EloquentBundle

Wires Laravel's [Eloquent](https://laravel.com/docs/eloquent) (Illuminate Database)
into the Symfony container, and adds `database:migrate` and
`database:migrate:rollback` console commands.

Useful when you want Eloquent's query builder, models, and migrations inside a
Symfony application — migrating away from Laravel, or simply preferring Eloquent's
ergonomics over Doctrine.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Requirements

- PHP 8.2+
- Symfony 6.4, 7.x, or 8.x
- Illuminate Database 11, 12, or 13

## Installation

```bash
composer require kraicdesign/eloquent-bundle
```

If you are not using Symfony Flex, register the bundle manually:

```php
// config/bundles.php
return [
    // ...
    Kraicdesign\EloquentBundle\EloquentBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/eloquent.yaml
eloquent:
    default_connection: default

    connections:
        default:
            driver: pgsql
            host: '%env(DATABASE_HOST)%'
            port: '%env(int:DATABASE_PORT)%'
            database: '%env(DATABASE_NAME)%'
            username: '%env(DATABASE_USER)%'
            password: '%env(DATABASE_PASSWORD)%'
            charset: utf8
            prefix: ''
            schema: public

    migrations:
        path: '%kernel.project_dir%/database/migrations'
        table: migrations
        connection: default
```

### Options

| Option | Default | Description |
| --- | --- | --- |
| `default_connection` | `default` | Which entry in `connections` is the default. |
| `connections` | *required* | Map of connection name to Illuminate connection config. Keys are passed through untouched, so any driver Illuminate supports works. |
| `migrations.path` | `%kernel.project_dir%/database/migrations` | Where migration files live. |
| `migrations.table` | `migrations` | Table recording applied migrations. |
| `migrations.connection` | `default` | Connection migrations run against. `default` resolves to `default_connection`. |

At least one connection is required.

## Usage

### Dependency injection

The bundle registers these services, all private, so you type-hint them normally:

| Type-hint | Resolves to |
| --- | --- |
| `Illuminate\Database\ConnectionInterface` | the default connection |
| `Illuminate\Database\Connection` | the default connection |
| `Illuminate\Database\Capsule\Manager` | the Capsule itself |

```php
use Illuminate\Database\ConnectionInterface;

final class UserRepository
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function findByEmail(string $email): ?object
    {
        return $this->db->table('users')->where('email', $email)->first();
    }
}
```

### Eloquent models

`Capsule::setAsGlobal()` and `bootEloquent()` are called during container
compilation, so Eloquent models work without further setup:

```php
use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    protected $table = 'users';
}

User::query()->where('active', true)->get();
```

### Migrations

Migration files use the standard Illuminate format:

```php
// database/migrations/2026_01_01_000000_create_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        $this->schema()->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('users');
    }
};
```

```bash
php bin/console database:migrate
php bin/console database:migrate:rollback
```

The migrations table is created automatically on first run.

## Design notes

**Eloquent is registered globally.** `CapsuleFactory::create()` calls
`setAsGlobal()` and `bootEloquent()`. This is what makes `Model::query()` work
anywhere without injecting the Capsule, and it is the behaviour most people want
from Eloquent. The trade-off is a process-wide static: the bundle supports several
named connections, but only one Capsule, and that Capsule is global. If you need
two isolated Capsules in one process, this bundle is not the right fit.

**No Doctrine interop.** This bundle does not integrate with Doctrine ORM, the
Doctrine bundle's migrations, or `doctrine/dbal`. It is a standalone path.

**Connection config is passed through verbatim.** The `connections` node uses a
variable prototype with `normalizeKeys(false)`, so Illuminate options are not
validated or transformed by this bundle. Anything Illuminate accepts works; typos
surface as Illuminate errors rather than Symfony config errors.

## Contributing

Issues and pull requests are welcome. Please keep changes focused, and add a test
for behaviour changes.

```bash
composer install
vendor/bin/phpunit
```

## License

MIT — see [LICENSE](LICENSE).
