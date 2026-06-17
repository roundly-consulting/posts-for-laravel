<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts;

use Acme\LaravelPackageTools\Package;
use Acme\LaravelPackageTools\PackageServiceProvider;

final class PostsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('posts')
            ->hasConfigFile()
            ->hasMigration('create_posts_table');
    }
}
