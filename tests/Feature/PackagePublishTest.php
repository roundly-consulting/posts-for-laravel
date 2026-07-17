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

/**
 * A — the secret-safe `about` capture.
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about` section
 * was guarded by negative assertions against `app(Kernel::class)->output()`, which returns
 * `''`. Every "does not leak" check was vacuous. The two local cases this replaces were
 * already non-vacuous — they captured through `Artisan::call()` + `Artisan::output()` and one
 * even wrote "guard the guard" above its own positive — but the ordering was a habit rather
 * than a structure. The preset makes it structural: `mustRender` is required and non-empty,
 * the capture is asserted non-empty, and every positive is proven present BEFORE any secret is
 * looked for. A negative-only case cannot be written with it.
 */
it('renders both key axes without leaking the host configuration', function (): void {
    // A blog's config names the host: its tables, its media disk, its publisher and its site.
    // The section reports presence, switches and counts — never values.
    config()->set('posts.tables.posts', 'acme_intranet_posts');
    config()->set('posts.media.disk', 's3-acme-private');
    config()->set('posts.seo.site-name', 'Acme Intranet');
    config()->set('posts.json-ld.publisher.name', 'Acme Holdings BV');

    expect('posts')->toLeakNoSecrets(
        secrets: [
            // A table name is the host's schema; a disk is its infrastructure; the publisher
            // and site name are its identity. All reported by presence only.
            'acme_intranet_posts',
            's3-acme-private',
            'Acme Intranet',
            'Acme Holdings BV',
        ],
        mustRender: [
            'Model',
            'Post',
            // BOTH key axes must surface by name. A single "Key type" line is what let a uuid
            // posts.id hide behind an author key that was correct all along — a host reading
            // the section had no way to see which key it was being told about. These two lines
            // are the pin that keeps `posts.key_type` (the HOST's author model, outbound) and
            // `posts.primary_key_type` (posts' own tables, inbound) reported separately. They
            // are not the same thing and must never be merged.
            'Key type (author)',
            'Key type (posts id)',
            'bigint',
            // The presence marker that proves the customised lines report rather than sit
            // empty — without it the secret checks above would be aimed at a section that
            // might have printed nothing at all.
            'CUSTOMISED',
        ],
    );
});
