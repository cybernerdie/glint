<?php

declare(strict_types=1);

namespace Tests;

use Cybernerdie\Glint\GlintManager;
use Cybernerdie\Glint\GlintServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        GlintManager::flushFilters();
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

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('glint.enabled', true);
        config()->set('glint.middleware', []);
        config()->set('glint.recording.mode', 'sync');
        config()->set('glint.recording.store_bodies', true);
        config()->set('glint.pricing_path', __DIR__.'/../pricing/providers.json');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
