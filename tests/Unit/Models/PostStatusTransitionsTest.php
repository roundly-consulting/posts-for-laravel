<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostArchived;
use RoundlyConsulting\Posts\Events\PostDrafted;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Events\PostScheduled;
use RoundlyConsulting\Posts\Exceptions\InvalidPostStatusTransitionException;
use RoundlyConsulting\Posts\Models\Post;

afterEach(fn () => CarbonImmutable::setTestNow());

it('publishes a post and sets published_at to now by default', function (): void {
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');

    $post = Post::factory()->draft()->create();
    $post->publish();

    expect($post->status)->toBe(PostStatus::Published)
        ->and($post->published_at->toDateTimeString())->toBe('2026-01-01 12:00:00');
});

it('publishes a post at an explicit time', function (): void {
    $post = Post::factory()->draft()->create();
    $post->publish(CarbonImmutable::parse('2030-05-05 08:00:00'));

    expect($post->fresh()->published_at->toDateTimeString())->toBe('2030-05-05 08:00:00');
});

it('schedules a post for a future time', function (): void {
    $post = Post::factory()->draft()->create();
    $post->schedule(CarbonImmutable::parse('2031-01-01 00:00:00'));

    expect($post->status)->toBe(PostStatus::Scheduled)
        ->and($post->published_at->toDateTimeString())->toBe('2031-01-01 00:00:00');
});

it('archives a published post keeping its published_at', function (): void {
    $post = Post::factory()->published()->create();
    $publishedAt = $post->published_at;

    $post->archive();

    expect($post->status)->toBe(PostStatus::Archived)
        ->and($post->published_at?->equalTo($publishedAt))->toBeTrue();
});

it('returns an archived post to draft and clears published_at', function (): void {
    $post = Post::factory()->published()->create();
    $post->archive();

    $post->draft();

    expect($post->status)->toBe(PostStatus::Draft)
        ->and($post->published_at)->toBeNull();
});

it('rejects publishing an archived post', function (): void {
    $post = Post::factory()->published()->create();
    $post->archive();

    $post->publish();
})->throws(InvalidPostStatusTransitionException::class);

it('dispatches an event for each transition', function (): void {
    Event::fake();

    $post = Post::factory()->draft()->create();

    $post->publish();
    $post->schedule(now()->addDay());
    $post->draft();
    $post->archive();

    Event::assertDispatched(PostPublished::class);
    Event::assertDispatched(PostScheduled::class);
    Event::assertDispatched(PostDrafted::class);
    Event::assertDispatched(PostArchived::class);
});
