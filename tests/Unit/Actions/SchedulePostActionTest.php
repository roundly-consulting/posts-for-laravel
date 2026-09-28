<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Posts\Actions\SchedulePostAction;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostScheduled;
use RoundlyConsulting\Posts\Exceptions\InvalidPostStatusTransitionException;
use RoundlyConsulting\Posts\Models\Post;

it('schedules a post and dispatches PostScheduled', function (): void {
    Event::fake([PostScheduled::class]);

    $post = Post::factory()->draft()->create();

    $result = app(SchedulePostAction::class)->execute($post, CarbonImmutable::parse('2031-01-01 08:00:00'));

    expect($result)->toBe($post)
        ->and($post->fresh()?->status)->toBe(PostStatus::Scheduled)
        ->and($post->fresh()?->published_at?->toDateTimeString())->toBe('2031-01-01 08:00:00');

    Event::assertDispatched(PostScheduled::class, fn (PostScheduled $event): bool => $event->postId === $post->id);
});

it('refuses to schedule an archived post', function (): void {
    $post = Post::factory()->archived()->create();

    app(SchedulePostAction::class)->execute($post, now()->addDay());
})->throws(InvalidPostStatusTransitionException::class, 'Cannot transition a post from [archived] to [scheduled].');
