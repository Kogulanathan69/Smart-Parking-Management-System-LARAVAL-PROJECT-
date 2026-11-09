<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fail closed before RefreshDatabase can migrate or write to any connection.
     */
    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $name => $expected) {
            if (getenv($name) !== $expected) {
                throw new LogicException("Tests require {$name}={$expected}.");
            }
        }

        if (trim((string) getenv('DB_URL')) !== '') {
            throw new LogicException('Tests require an empty DB_URL.');
        }

        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.driver') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('Refusing to run tests outside isolated SQLite memory.');
        }

        return $app;
    }
}
