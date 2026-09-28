<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Posts\Actions\PublishDuePostsAction;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Models\Post;

afterEach(fn () => CarbonImmutable::setTestNow());

it('publishes due scheduled posts at their scheduled time and counts them', function (): void {
    Event::fake([PostPublished::class]);
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');

    $due = Post::factory()->count(3)->create(['status' => PostStatus::Scheduled, 'published_at' => now()->subHour()]);
    $future = Post::factory()->create(['status' => PostStatus::Scheduled, 'published_at' => now()->addHour()]);
    $draft = Post::factory()->draft()->create();

    expect(app(PublishDuePostsAction::class)->execute())->toBe(3);

    foreach ($due as $post) {
        expect($post->fresh()?->status)->toBe(PostStatus::Published)
            ->and($post->fresh()?->published_at?->toDateTimeString())->toBe('2026-01-01 11:00:00');
    }

    expect($future->fresh()?->status)->toBe(PostStatus::Scheduled)
        ->and($draft->fresh()?->status)->toBe(PostStatus::Draft);

    Event::assertDispatchedTimes(PostPublished::class, 3);
});

it('returns zero when nothing is due', function (): void {
    expect(app(PublishDuePostsAction::class)->execute())->toBe(0);
});
