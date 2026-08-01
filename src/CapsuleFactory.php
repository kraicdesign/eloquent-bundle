<?php

declare(strict_types=1);

namespace Kraicdesign\EloquentBundle;

use Illuminate\Database\Capsule\Manager as Capsule;

final class CapsuleFactory
{
    /**
     * @param array<string, array<string, mixed>> $connections
     */
    public static function create(array $connections, string $defaultConnection): Capsule
    {
        $capsule = new Capsule();

        foreach ($connections as $name => $config) {
            $capsule->addConnection($config, $name);
        }

        $capsule->getDatabaseManager()->setDefaultConnection($defaultConnection);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $capsule;
    }
}
