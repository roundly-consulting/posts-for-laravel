<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Posts\Models\Category;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\Support\JsonLdBuilder;
use RoundlyConsulting\Posts\Support\PostsConfig;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;

/**
 * Sweep 2 — the non-boolean settings. `(string) config()` turned a null table name into `''`
 * and an array into `Array`; an `on_missing`, JSON-LD type or card typo was passed through or
 * silently replaced; a non-string bucket or disk fell back. Each now throws, naming the key.
 *
 * Sweep 3 — a blank value (a host's `KEY=`, or whitespace) is not set: it takes the default, or
 * for an optional setting none, exactly like an absent key. Junk still throws.
 */
beforeEach(function (): void {
    Storage::fake('public');
});

it('refuses a non-string table name (strict config)', function (string $key, Closure $read): void {
    foreach ([['x'], 5, true] as $value) {
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

it('uses the packaged table names when they are absent or blank (strict config)', function (?string $value): void {
    config()->set('posts.tables.posts', $value);
    config()->set('posts.tables.categories', $value);
    config()->set('posts.tables.tags', $value);
    config()->set('posts.tables.category_post', $value);
    config()->set('posts.tables.tag_post', $value);

    expect((new Post)->getTable())->toBe('posts')
        ->and((new Category)->getTable())->toBe('post_categories')
        ->and((new Tag)->getTable())->toBe('post_tags')
        ->and((new Post)->categories()->getTable())->toBe('category_post')
        ->and((new Post)->tags()->getTable())->toBe('post_tag');
})->with(['absent' => [null], 'blank' => [''], 'whitespace' => [' ']]);

it('refuses a non-string string setting (strict config)', function (string $key, mixed $value, Closure $read): void {
    config()->set($key, $value);

    expect($read)->toThrow(InvalidConfigurationException::class, "Configuration value [{$key}] must be a non-empty string");
})->with([
    'author morph name' => ['posts.author.morph-name', 5, fn () => (new Post)->author()],
    'slug source' => ['posts.slugs.source', ['title'], fn () => Post::factory()->create()],
    'slug separator' => ['posts.slugs.separator', ['-'], fn () => Post::factory()->create()],
    'default robots' => ['posts.seo.default-robots', true, fn () => Post::factory()->create()->seo()],
    'author attribute' => ['posts.json-ld.author-attribute', 1, fn () => app(JsonLdBuilder::class)->build(
        Post::factory()->forAuthor(AuthorTestModel::create(['name' => 'Jane']))->create(),
    )],
    'site name int' => ['posts.seo.site-name', 5, fn () => Post::factory()->create()->seo()],
    'twitter site array' => ['posts.seo.twitter-site', ['@acme'], fn () => Post::factory()->create()->seo()],
    'publisher name int' => ['posts.json-ld.publisher.name', 5, fn () => Post::factory()->create()->toJsonLd()],
    'publisher logo array' => ['posts.json-ld.publisher.logo', ['logo.png'], function (): void {
        config()->set('posts.json-ld.publisher.name', 'Acme');
        Post::factory()->create()->toJsonLd();
    }],
    'featured bucket' => ['posts.media.featured_bucket', 1, fn () => Post::factory()->create()->featuredBucket()],
    'gallery bucket' => ['posts.media.gallery_bucket', [], fn () => Post::factory()->create()->galleryBucket()],
    'content bucket' => ['posts.media.content_bucket', false, fn () => Post::factory()->create()->contentBucket()],
    'media disk' => ['posts.media.disk', 3, fn () => Post::factory()->create()->resolveMediaBucket('gallery')],
    'featured fallback' => ['posts.media.featured_fallback_url', 7, fn () => Post::factory()->create()->resolveMediaBucket('featured')],
    'fallback locale' => ['posts.locales.fallback', ['en'], fn () => Post::factory()->create()],
]);

it('reads a blank string setting as not set, so the default applies (strict config)', function (string $blank): void {
    foreach ([
        'posts.author.morph-name', 'posts.slugs.source', 'posts.slugs.separator', 'posts.seo.default-robots',
        'posts.json-ld.author-attribute', 'posts.media.featured_bucket', 'posts.media.gallery_bucket',
        'posts.media.content_bucket',
    ] as $key) {
        config()->set($key, $blank);
    }

    expect(PostsConfig::authorMorphName())->toBe('author')
        ->and(PostsConfig::slugSource())->toBe('title')
        ->and(PostsConfig::slugSeparator())->toBe('-')
        ->and(PostsConfig::defaultRobots())->toBe('index,follow')
        ->and(PostsConfig::jsonLdAuthorAttribute())->toBe('name')
        ->and(PostsConfig::featuredBucket())->toBe('featured')
        ->and(PostsConfig::galleryBucket())->toBe('gallery')
        ->and(PostsConfig::contentBucket())->toBe('content')
        ->and(Post::factory()->withTitles(['en' => 'Hello World'])->create()->slug)->toBe('hello-world');
})->with(['empty' => [''], 'whitespace' => ['  ']]);

it('reads a blank optional setting as not set, so it stays off (strict config)', function (string $blank): void {
    foreach ([
        'posts.seo.site-name', 'posts.seo.twitter-site', 'posts.json-ld.publisher.name', 'posts.json-ld.publisher.logo',
        'posts.media.disk', 'posts.media.featured_fallback_url', 'posts.media.responsive_widths',
        'posts.media.og_variant', 'posts.media.inline.default_variant', 'posts.moderation.on_resolved',
    ] as $key) {
        config()->set($key, $blank);
    }

    $post = Post::factory()->create();

    expect(PostsConfig::siteName())->toBeNull()
        ->and(PostsConfig::twitterSite())->toBeNull()
        ->and(PostsConfig::publisherName())->toBeNull()
        ->and(PostsConfig::publisherLogo())->toBeNull()
        ->and(PostsConfig::mediaDisk())->toBeNull()
        ->and(PostsConfig::featuredFallbackUrl())->toBeNull()
        ->and(PostsConfig::responsiveWidths())->toBeNull()
        ->and(PostsConfig::ogVariant())->toBe('')
        ->and(PostsConfig::inlineDefaultVariant())->toBe('')
        ->and(PostsConfig::onResolved())->toBeNull()
        ->and($post->seo()->ogSiteName)->toBeNull()
        ->and($post->toJsonLd())->not->toHaveKey('publisher');
})->with(['empty' => [''], 'whitespace' => [' ']]);

it('reads a blank fallback locale as not set, so the app fallback applies (strict config)', function (): void {
    config()->set('app.fallback_locale', 'de');
    config()->set('posts.locales.fallback', '');

    expect(PostsConfig::fallbackLocale())->toBe('de')
        ->and(PostsConfig::configuredFallbackLocale())->toBeNull();

    config()->set('app.fallback_locale', ' ');

    expect(PostsConfig::fallbackLocale())->toBe('en');
});

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

it('reports blank table names as the defaults in about, and a junk one as invalid (strict config)', function (): void {
    config()->set('posts.tables.posts', '');
    config()->set('posts.tables.tags', ' ');

    Artisan::call('about', ['--only' => 'posts']);

    expect(Artisan::output())->toMatch('/Tables\W+5 table\(s\), DEFAULT/');

    config()->set('posts.tables.tags', ['post_tags']);
    Artisan::call('about', ['--only' => 'posts']);

    expect(Artisan::output())->toMatch('/Tables\W+INVALID/');
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
