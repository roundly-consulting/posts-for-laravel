<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Likes\LikesServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Posts\PostsServiceProvider;
use RoundlyConsulting\Reports\ReportsServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            ApprovalsServiceProvider::class,
            LikesServiceProvider::class,
            MediaLibraryServiceProvider::class,
            ReportsServiceProvider::class,
            PostsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $this->configureConnection();

        // The two independent key axes, both set BEFORE the providers boot and before the
        // migrations run — the only window that matters, since the migrations read them to
        // pick column types and the models read them to decide whether to mint an id.
        config()->set('posts.key_type', $this->authorKeyType());
        config()->set('posts.primary_key_type', $this->postsKeyType());

        // Media-library: store on a fakeable public disk, use the GD driver, and keep the
        // responsive ladder small so variant generation stays fast under test.
        config()->set('media.disk', 'public');
        config()->set('media.image_driver', 'gd');
        config()->set('media.responsive.widths', [320, 640]);
    }

    /**
     * Point the `testing` connection at whatever engine `TESTING_DB_DRIVER` names, defaulting
     * to in-memory SQLite.
     *
     * A SQLite-only suite cannot see this package's central schema bug at all: morph columns
     * carry no foreign keys, so `PRAGMA foreign_keys` is irrelevant, and SQLite's type
     * affinity stores a uuid string in an INTEGER column without a murmur. Only a strict
     * engine rejects it. That is why this seam exists and why `run-tests.yml` runs a real
     * Postgres leg.
     */
    protected function configureConnection(): void
    {
        $driver = (string) (env('TESTING_DB_DRIVER') ?: 'sqlite');

        if ($driver !== 'sqlite') {
            config()->set('database.connections.testing', [
                'driver' => $driver,
                'host' => env('TESTING_DB_HOST', '127.0.0.1'),
                'port' => (int) env('TESTING_DB_PORT', 5432),
                'database' => env('TESTING_DB_DATABASE', 'testing'),
                'username' => env('TESTING_DB_USERNAME', 'testing'),
                'password' => env('TESTING_DB_PASSWORD', 'secret'),
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ]);
        }

        config()->set('database.default', 'testing');
    }

    /** The key type of the host's AUTHOR models — the outbound axis the host owns. */
    protected function authorKeyType(): string
    {
        return 'bigint';
    }

    /** The key type of posts' OWN tables — the inbound axis other packages' morphs point at. */
    protected function postsKeyType(): string
    {
        return 'bigint';
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Media-library ships the `media` table the post buckets persist into.
        $mediaPackage = dirname((string) (new ReflectionClass(MediaLibraryServiceProvider::class))->getFileName(), 2);
        $this->loadMigrationsFrom($mediaPackage.'/database/migrations');

        // Likes ships the `likes` table posts' reactions persist into.
        $likesPackage = dirname((string) (new ReflectionClass(LikesServiceProvider::class))->getFileName(), 2);
        $this->loadMigrationsFrom($likesPackage.'/database/migrations');

        // Reports ships the `reports` table posts' report-a-post persists into.
        $reportsPackage = dirname((string) (new ReflectionClass(ReportsServiceProvider::class))->getFileName(), 2);
        $this->loadMigrationsFrom($reportsPackage.'/database/migrations');

        // Approvals engine tables back reports' multi-moderator moderation flow.
        $approvalsPackage = dirname((string) (new ReflectionClass(ApprovalsServiceProvider::class))->getFileName(), 2);
        $this->loadMigrationsFrom($approvalsPackage.'/database/migrations');

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('uuid_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
        });

        Schema::create('ulid_users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
        });
    }

    /**
     * Reset the schema between tests by **dropping every table**, not by rolling back.
     *
     * Testbench unwinds each cached migrator with `migrate:rollback`, which calls each
     * migration's `down()`. Roundly packages ship no `down()` — the standard is forward-only —
     * and `Migrator::runMigration()` guards `down()` with `method_exists`, so that rollback is
     * a silent no-op. On in-memory SQLite it never mattered: the database dies with the
     * connection. On a real engine the tables survive and the *next* test dies on a duplicate
     * relation, naming an innocent migration.
     *
     * Dropping every table reaches the same state with zero `down()`. Emptying the migrator
     * cache first is what makes Testbench's rollback loop a no-op rather than a competing one.
     *
     * @internal Overrides Testbench's teardown hook, dispatched by trait basename.
     */
    protected function tearDownInteractsWithMigrations(): void
    {
        if ($this->usesSqliteInMemoryDatabaseConnection()) {
            parent::tearDownInteractsWithMigrations();

            return;
        }

        $this->cachedTestMigratorProcessors = [];

        parent::tearDownInteractsWithMigrations();

        Schema::connection((string) config('database.default'))->dropAllTables();

        $this->app?->make('db')->purge();
    }
}
