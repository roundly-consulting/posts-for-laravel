<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Models\Category;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;

afterEach(fn () => CarbonImmutable::setTestNow());

it('returns only published posts whose time has come', function (): void {
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');

    $live = Post::factory()->create(['status' => PostStatus::Published, 'published_at' => now()->subHour()]);
    Post::factory()->create(['status' => PostStatus::Published, 'published_at' => now()->addHour()]);
    Post::factory()->draft()->create();

    $results = Post::query()->published()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->is($live))->toBeTrue();
});

it('treats the published boundary at exactly now as published', function (): void {
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');

    Post::factory()->create(['status' => PostStatus::Published, 'published_at' => now()]);

    expect(Post::query()->published()->count())->toBe(1);
});

it('filters draft posts', function (): void {
    Post::factory()->draft()->create();
    Post::factory()->published()->create();

    expect(Post::query()->draft()->count())->toBe(1);
});

it('filters scheduled posts including future-dated published ones', function (): void {
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');

    Post::factory()->create(['status' => PostStatus::Scheduled, 'published_at' => now()->addDay()]);
    Post::factory()->create(['status' => PostStatus::Published, 'published_at' => now()->addDay()]);
    Post::factory()->create(['status' => PostStatus::Published, 'published_at' => now()->subDay()]);

    expect(Post::query()->scheduled()->count())->toBe(2);
});

it('filters archived posts', function (): void {
    $post = Post::factory()->published()->create();
    $post->archive();

    expect(Post::query()->archived()->count())->toBe(1);
});

it('filters posts in a given category by translated slug', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'Laravel']]);
    $post = Post::factory()->create();
    $post->categories()->attach($category);

    Post::factory()->create();

    expect(Post::query()->inCategory($category)->count())->toBe(1)
        ->and(Post::query()->inCategory($category->getTranslation('slug', 'en'))->count())->toBe(1);
});

it('filters posts with a given tag by translated slug', function (): void {
    $tag = Tag::factory()->create(['name' => ['en' => 'php']]);
    $post = Post::factory()->create();
    $post->tags()->attach($tag);

    Post::factory()->create();

    expect(Post::query()->withTag($tag)->count())->toBe(1)
        ->and(Post::query()->withTag('php')->count())->toBe(1);
});

it('matches a category slug along the locale chain', function (): void {
    // The category only has an English slug; a Slovak request still finds its posts.
    $category = Category::factory()->create(['name' => ['en' => 'Guides']]);
    $post = Post::factory()->create();
    $post->categories()->attach($category);

    app()->setLocale('sk');

    expect(Post::query()->inCategory('guides')->pluck('id')->all())->toBe([$post->getKey()])
        ->and(Post::query()->inCategory($category)->count())->toBe(1);
});

it('counts posts by tag on any engine', function (): void {
    // The slug scopes are predicate-only, so a count over the EXISTS subquery stays valid SQL
    // on Postgres (no ORDER BY inside an aggregate).
    $tag = Tag::factory()->create(['name' => ['en' => 'Eloquent']]);
    Post::factory()->count(2)->create()->each(fn (Post $post) => $post->tags()->attach($tag));

    expect(Post::query()->withTag('eloquent')->count())->toBe(2)
        ->and(Post::query()->withTag('missing')->count())->toBe(0);
});

it('filters by the given category, not by others sharing its slug in another locale', function (): void {
    // `news` is the English slug of one category and the Slovak slug of another: per-locale
    // uniqueness allows it, so an instance must be matched by its key, never by its slug.
    $news = Category::factory()->create(['name' => ['en' => 'News']]);
    $slovak = Category::factory()->create(['name' => ['sk' => 'News']]);
    $ours = Post::factory()->create();
    $ours->categories()->attach($news);
    Post::factory()->create()->categories()->attach($slovak);

    expect(Post::query()->inCategory($news)->pluck('id')->all())->toBe([$ours->getKey()]);

    app()->setLocale('sk');

    expect(Post::query()->inCategory($news)->pluck('id')->all())->toBe([$ours->getKey()]);
});

it('filters by a given tag that has no slug', function (): void {
    // A name that slugifies to nothing is skipped, so the tag has no slug to match on.
    $tag = Tag::factory()->create(['name' => ['en' => '###']]);
    $post = Post::factory()->create();
    $post->tags()->attach($tag);
    Post::factory()->create();

    expect($tag->slugMap())->toBe([])
        ->and(Post::query()->withTag($tag)->pluck('id')->all())->toBe([$post->getKey()]);
});

it('resolves a category slug to the current locale match before rescuing another locale', function (): void {
    $news = Category::factory()->create(['name' => ['en' => 'News']]);
    $slovak = Category::factory()->create(['name' => ['sk' => 'News']]);
    $english = Post::factory()->create();
    $english->categories()->attach($news);
    $inSlovak = Post::factory()->create();
    $inSlovak->categories()->attach($slovak);

    expect(Post::query()->inCategory('news')->pluck('id')->all())->toBe([$english->getKey()]);

    app()->setLocale('sk');

    expect(Post::query()->inCategory('news')->pluck('id')->all())->toBe([$inSlovak->getKey()])
        ->and(Post::query()->inCategory('news')->count())->toBe(1);
});

it('resolves a tag slug to the current locale match before rescuing another locale', function (): void {
    $english = Tag::factory()->create(['name' => ['en' => 'Travel']]);
    $slovak = Tag::factory()->create(['name' => ['sk' => 'Travel']]);
    $first = Post::factory()->create();
    $first->tags()->attach($english);
    $second = Post::factory()->create();
    $second->tags()->attach($slovak);

    expect(Post::query()->withTag('travel')->pluck('id')->all())->toBe([$first->getKey()]);

    app()->setLocale('sk');

    expect(Post::query()->withTag('travel')->pluck('id')->all())->toBe([$second->getKey()])
        ->and(Post::query()->withTag('travel')->count())->toBe(1);
});
