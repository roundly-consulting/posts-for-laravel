<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Posts\Commands\PublishScheduledPostsCommand;
use RoundlyConsulting\Posts\Models\Post;

final class PostsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/posts.php', 'posts');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'posts');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'posts');

        if ((bool) config('posts.slugs.route-binding', true)) {
            Route::model('post', Post::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishScheduledPostsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/posts.php' => config_path('posts.php'),
            ], 'posts-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'posts-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => base_path('resources/views/vendor/posts'),
            ], 'posts-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/posts'),
            ], 'posts-translations');
        }
    }
}
