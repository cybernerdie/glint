<?php

declare(strict_types=1);

namespace Tests;

use Cybernerdie\Glint\GlintManager;
use Cybernerdie\Glint\GlintServiceProvider;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        GlintManager::flushFilters();
    }

    protected function tearDown(): void
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        if ($driver === 'pgsql' || $driver === 'mysql') {
            DB::purge();
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            GlintServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $databaseConnection = env('DB_CONNECTION', 'sqlite');

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', $this->testing_database_connection($databaseConnection));
        config()->set('glint.enabled', true);
        config()->set('glint.middleware', []);
        config()->set('glint.recording', [
            'mode' => 'sync',
            'store_bodies' => true,
            'max_completion_chars' => 65535,
        ]);
        config()->set('glint.pricing_path', __DIR__.'/../pricing/providers.json');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * @return array<string, mixed>
     */
    private function testing_database_connection(string $driver): array
    {
        if ($driver === 'mysql') {
            return [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'glint_test'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'unix_socket' => env('DB_SOCKET', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ];
        }

        if ($driver === 'pgsql') {
            return [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', 'glint_test'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ];
        }

        return [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', ':memory:'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
    }
}
