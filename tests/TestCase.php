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
        config()->set('database.default', 'testing');
        config()->set('posts.author.key-type', $this->authorKeyType());
        config()->set('posts.locales.available', ['en', 'sk']);

        // Media-library: store on a fakeable public disk, use the GD driver, and keep the
        // responsive ladder small so variant generation stays fast under test.
        config()->set('media.disk', 'public');
        config()->set('media.image_driver', 'gd');
        config()->set('media.responsive.widths', [320, 640]);
    }

    protected function authorKeyType(): string
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
    }
}
