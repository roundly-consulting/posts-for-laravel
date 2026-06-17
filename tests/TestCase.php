<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Posts\PostsServiceProvider;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;
use Acme\MediaLibrary\MediaLibraryServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            PostsServiceProvider::class,
            MediaLibraryServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('posts.author-model', AuthorTestModel::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $mediaMigration = include __DIR__.'/../vendor/acme/laravel-medialibrary/database/migrations/create_media_table.php.stub';
        $mediaMigration->up();

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });
    }

    protected function getEnvironmentSetUp($app): void
    {
        $this->defineEnvironment($app);
    }
}
