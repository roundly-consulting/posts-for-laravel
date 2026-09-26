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
