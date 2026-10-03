<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Posts\Models\Category;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\Support\JsonLdBuilder;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;

/**
 * Sweep 2 — the non-boolean settings. `(string) config()` turned a null table name into `''`
 * and an array into `Array`; an `on_missing`, JSON-LD type or card typo was passed through or
 * silently replaced; a blank bucket or disk fell back. Each now throws, naming the key.
 */
beforeEach(function (): void {
    Storage::fake('public');
});

it('refuses a blank or non-string table name (strict config)', function (string $key, Closure $read): void {
    foreach ([['x'], '', ' '] as $value) {
        config()->set($key, $value);

        expect($read)->toThrow(InvalidConfigurationException::class, "Configuration value [{$key}] must be a non-empty string");
    }
})->with([
    'posts' => ['posts.tables.posts', fn (): string => (new Post)->getTable()],
    'categories' => ['posts.tables.categories', fn (): string => (new Category)->getTable()],
    'tags' => ['posts.tables.tags', fn (): string => (new Tag)->getTable()],
    'category_post' => ['posts.tables.category_post', fn () => (new Post)->categories()],
    'tag_post' => ['posts.tables.tag_post', fn () => (new Post)->tags()],
]);

it('uses the packaged table names when they are absent (strict config)', function (): void {
    config()->set('posts.tables.posts', null);
    config()->set('posts.tables.categories', null);
    config()->set('posts.tables.tags', null);

    expect((new Post)->getTable())->toBe('posts')
        ->and((new Category)->getTable())->toBe('post_categories')
        ->and((new Tag)->getTable())->toBe('post_tags');
});

it('refuses a blank or non-string string setting (strict config)', function (string $key, mixed $value, Closure $read): void {
    config()->set($key, $value);

    expect($read)->toThrow(InvalidConfigurationException::class, "Configuration value [{$key}] must be a non-empty string");
})->with([
    'author morph name' => ['posts.author.morph-name', '', fn () => (new Post)->author()],
    'slug source' => ['posts.slugs.source', '', fn () => Post::factory()->create()],
    'slug separator' => ['posts.slugs.separator', ['-'], fn () => Post::factory()->create()],
    'default robots' => ['posts.seo.default-robots', '', fn () => Post::factory()->create()->seo()],
    'author attribute' => ['posts.json-ld.author-attribute', '', fn () => app(JsonLdBuilder::class)->build(
        Post::factory()->forAuthor(AuthorTestModel::create(['name' => 'Jane']))->create(),
    )],
    'site name blank' => ['posts.seo.site-name', '', fn () => Post::factory()->create()->seo()],
    'twitter site array' => ['posts.seo.twitter-site', ['@acme'], fn () => Post::factory()->create()->seo()],
    'publisher name int' => ['posts.json-ld.publisher.name', 5, fn () => Post::factory()->create()->toJsonLd()],
    'publisher logo blank' => ['posts.json-ld.publisher.logo', '', function (): void {
        config()->set('posts.json-ld.publisher.name', 'Acme');
        Post::factory()->create()->toJsonLd();
    }],
    'featured bucket' => ['posts.media.featured_bucket', '', fn () => Post::factory()->create()->featuredBucket()],
    'gallery bucket' => ['posts.media.gallery_bucket', [], fn () => Post::factory()->create()->galleryBucket()],
    'content bucket' => ['posts.media.content_bucket', ' ', fn () => Post::factory()->create()->contentBucket()],
    'media disk' => ['posts.media.disk', '', fn () => Post::factory()->create()->resolveMediaBucket('gallery')],
    'featured fallback' => ['posts.media.featured_fallback_url', 7, fn () => Post::factory()->create()->resolveMediaBucket('featured')],
    'fallback locale' => ['posts.locales.fallback', '', fn () => Post::factory()->create()],
]);

it('refuses a junk vocabulary value (strict config)', function (string $key, mixed $value, string $allowed, Closure $read): void {
    config()->set($key, $value);

    expect($read)->toThrow(InvalidConfigurationException::class, "Configuration value [{$key}] must be one of [{$allowed}]");
})->with([
    'json-ld type' => ['posts.json-ld.type', 'BlogPostng', 'BlogPosting, Article', fn () => Post::factory()->create()->toJsonLd()],
    'twitter card' => ['posts.seo.default-card', 'large', 'summary, summary_large_image, app, player', fn () => Post::factory()->create()->seo()],
    'inline on_missing' => ['posts.media.inline.on_missing', 'kepe', 'strip, keep', function (): void {
        $post = Post::factory()->create(['content' => ['en' => 'x [media:9b2f6c1e-0000-4000-8000-000000000000] y']]);
        (string) $post->renderContent();
    }],
]);

it('refuses a non-string variant name (strict config)', function (string $key, Closure $read): void {
    config()->set($key, ['thumb']);

    expect($read)->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'og variant' => ['posts.media.og_variant', fn () => Post::factory()->create()->seo()],
    'inline default variant' => ['posts.media.inline.default_variant', function (): void {
        $post = Post::factory()->create(['content' => ['en' => 'x [media:9b2f6c1e-0000-4000-8000-000000000000] y']]);
        (string) $post->renderContent();
    }],
]);

it('reads absent optional strings as unset (strict config)', function (): void {
    config()->set('posts.seo.site-name', null);
    config()->set('posts.json-ld.publisher.name', null);
    config()->set('posts.media.og_variant', null);

    $post = Post::factory()->create();

    expect($post->seo()->ogSiteName)->toBeNull()
        ->and($post->toJsonLd())->not->toHaveKey('publisher');
});

it('flags a broken setting in about instead of rendering a fallback (strict config)', function (): void {
    config()->set('posts.json-ld.type', 'BlogPostng');
    config()->set('posts.media.inline.on_missing', 'kepe');
    config()->set('posts.moderation.on_resolved', 'archvie');

    Artisan::call('about', ['--only' => 'posts']);
    $output = Artisan::output();

    expect($output)->toMatch('/JSON-LD\W+INVALID/')
        ->and($output)->toMatch('/Inline media\W+INVALID/')
        ->and($output)->toMatch('/Moderation\W+INVALID/');
});
