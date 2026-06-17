<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts;

use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Posts\Commands\PublishScheduledPostsCommand;
use RoundlyConsulting\Posts\Models\Post;
use Acme\LaravelPackageTools\Package;
use Acme\LaravelPackageTools\PackageServiceProvider;

final class PostsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('posts')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([
                'create_posts_table',
                'create_post_categories_table',
                'create_category_post_table',
                'create_post_tags_table',
                'create_post_tag_table',
            ])
            ->hasCommands([
                PublishScheduledPostsCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        if ((bool) config('posts.slugs.route-binding', true)) {
            Route::model('post', Post::class);
        }
    }
}
