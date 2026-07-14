<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Posts\PostsServiceProvider;

/**
 * The publish-only migration policy and the package's host-visible publish surface.
 */
it('never auto-loads its migrations', function (): void {
    $directory = (string) realpath(__DIR__.'/../../database/migrations');

    $registered = array_map(
        static fn (string $path): string => (string) realpath($path),
        app('migrator')->paths(),
    );

    expect($registered)->not->toContain($directory);

    // The registered-paths check is empty in a Testbench app, so pin the policy at
    // its source too: the provider must not load the directory at all.
    expect((string) file_get_contents(__DIR__.'/../../src/PostsServiceProvider.php'))
        ->not->toContain('loadMigrationsFrom');
});

it('publishes every migration under the posts-migrations tag', function (): void {
    $sources = array_map(
        static fn (string $file): string => (string) realpath($file),
        glob(__DIR__.'/../../database/migrations/*.php') ?: [],
    );
    sort($sources);

    $published = ServiceProvider::pathsToPublish(PostsServiceProvider::class, 'posts-migrations');

    expect($published)->toHaveCount(count($sources));

    foreach ($sources as $source) {
        expect(array_keys($published))->toContain($source);
    }

    foreach ($published as $destination) {
        expect($destination)
            ->toStartWith(database_path('migrations'))
            ->toMatch('#/\d{4}_\d{2}_\d{2}_\d{6}_create_[a-z_]+\.php$#');
    }
});

it('keeps every publish tag byte-identical', function (): void {
    expect(ServiceProvider::pathsToPublish(PostsServiceProvider::class, 'posts-config'))
        ->toBe([realpath(__DIR__.'/../../config/posts.php') => config_path('posts.php')]);

    expect(ServiceProvider::pathsToPublish(PostsServiceProvider::class, 'posts-views'))
        ->toBe([realpath(__DIR__.'/../../resources/views') => resource_path('views/vendor/posts')]);

    expect(ServiceProvider::pathsToPublish(PostsServiceProvider::class, 'posts-migrations'))
        ->not->toBe([]);
});

it('registers the posts view namespace', function (): void {
    expect(view()->exists('posts::meta'))->toBeTrue()
        ->and(view()->exists('posts::json-ld'))->toBeTrue();
});

it('contributes a section to about', function (): void {
    Artisan::call('about', ['--only' => 'posts']);

    expect(Artisan::output())
        ->toContain('Model')
        ->toContain('Post')
        ->toContain('Key type')
        ->toContain('bigint');
});

it('never leaks a configured value into the about section', function (): void {
    // A blog's config names the host: its tables, its media disk, its publisher and
    // its site. The section reports presence, switches and counts — never values.
    config()->set('posts.tables.posts', 'acme_intranet_posts');
    config()->set('posts.media.disk', 's3-acme-private');
    config()->set('posts.seo.site-name', 'Acme Intranet');
    config()->set('posts.json-ld.publisher.name', 'Acme Holdings BV');

    Artisan::call('about', ['--only' => 'posts']);
    $output = Artisan::output();

    // Guard the guard: a positive first, so an empty section cannot pass this test.
    expect($output)->toContain('CUSTOMISED');

    expect($output)
        ->not->toContain('acme_intranet_posts')
        ->not->toContain('s3-acme-private')
        ->not->toContain('Acme Intranet')
        ->not->toContain('Acme Holdings BV');
});
