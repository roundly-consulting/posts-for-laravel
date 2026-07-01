<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Posts\Commands\PublishScheduledPostsCommand;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Listeners\SyncPostVisibilityFromReports;
use RoundlyConsulting\Posts\Listeners\WarmPostMediaVariants;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Support\ContentMediaRenderer;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;

final class PostsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/posts.php', 'posts');

        $this->app->singleton(ContentMediaRenderer::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'posts');

        if ((bool) config('posts.slugs.route-binding', true)) {
            Route::model('post', Post::class);
        }

        Event::listen(PostPublished::class, WarmPostMediaVariants::class);

        // Moderation → visibility: upheld reports / threshold crossings auto-unpublish a post.
        Event::listen(ReportResolved::class, [SyncPostVisibilityFromReports::class, 'handleResolved']);
        Event::listen(ReportThresholdReached::class, [SyncPostVisibilityFromReports::class, 'handleThresholdReached']);

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
        }
    }
}
