<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostTranslationData;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Facades\Posts;
use RoundlyConsulting\Posts\Models\Post;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-09-28 12:00:00'));

afterEach(fn () => CarbonImmutable::setTestNow());

/** The raw `published_at` column value, exactly as stored. */
function storedPublishedAt(Post $post): mixed
{
    return Post::query()->toBase()->where('id', $post->getKey())->value('published_at');
}

it('stores a Tokyo schedule as the same instant in the app timezone', function (): void {
    $post = Post::factory()->draft()->create();

    Posts::schedule($post, now('Asia/Tokyo')->addHour());

    expect(storedPublishedAt($post))->toBe('2026-09-28 13:00:00');

    CarbonImmutable::setTestNow('2026-09-28 12:59:00');
    expect(Posts::publishDue())->toBe(0);

    CarbonImmutable::setTestNow('2026-09-28 13:30:00');
    expect(Posts::publishDue())->toBe(1)
        ->and($post->fresh()?->status)->toBe(PostStatus::Published);
});

it('does not publish a New York schedule hours early', function (): void {
    $post = Post::factory()->draft()->create();

    Posts::schedule($post, now('America/New_York')->addHours(3));

    expect(storedPublishedAt($post))->toBe('2026-09-28 15:00:00');

    CarbonImmutable::setTestNow('2026-09-28 14:00:00');
    expect(Posts::publishDue())->toBe(0)
        ->and($post->fresh()?->status)->toBe(PostStatus::Scheduled);
});

it('stores an explicit publish date in the app timezone', function (): void {
    $post = Post::factory()->draft()->create();

    Posts::publish($post, CarbonImmutable::parse('2026-09-28 09:00:00', 'Europe/Bratislava'));

    expect(storedPublishedAt($post))->toBe('2026-09-28 07:00:00')
        ->and($post->published_at?->getTimezone()->getName())->toBe('UTC');
});

it('stores a created post date in the app timezone', function (): void {
    $post = Posts::create(new CreatePostData(
        translations: [new CreatePostTranslationData(locale: 'en', title: 'Tokyo')],
        status: PostStatus::Scheduled,
        publishedAt: CarbonImmutable::parse('2026-09-29 09:00:00', 'Asia/Tokyo'),
    ));

    expect(storedPublishedAt($post))->toBe('2026-09-29 00:00:00');
});

it('stores a directly assigned date in the app timezone', function (): void {
    $post = Post::factory()->create([
        'status' => PostStatus::Scheduled,
        'published_at' => CarbonImmutable::parse('2026-09-28 20:00:00', 'Asia/Tokyo'),
    ]);

    expect(storedPublishedAt($post))->toBe('2026-09-28 11:00:00');

    $post->published_at = null;
    $post->save();

    expect(storedPublishedAt($post))->toBeNull();
});

it('follows a non-UTC app timezone', function (): void {
    $previous = date_default_timezone_get();
    date_default_timezone_set('Europe/Bratislava');

    try {
        $post = Post::factory()->draft()->create();

        Posts::schedule($post, CarbonImmutable::parse('2026-09-28 13:00:00', 'UTC'));

        expect(storedPublishedAt($post))->toBe('2026-09-28 15:00:00');
    } finally {
        date_default_timezone_set($previous);
    }
});
