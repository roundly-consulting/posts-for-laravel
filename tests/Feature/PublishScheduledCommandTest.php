<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Models\Post;

afterEach(fn () => CarbonImmutable::setTestNow());

it('publishes only scheduled posts whose time has passed', function (): void {
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');

    $due = Post::factory()->create(['status' => PostStatus::Scheduled, 'published_at' => now()->subHour()]);
    $future = Post::factory()->create(['status' => PostStatus::Scheduled, 'published_at' => now()->addHour()]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($due->fresh()->status)->toBe(PostStatus::Published)
        ->and($future->fresh()->status)->toBe(PostStatus::Scheduled);
});

it('dispatches a published event for each post it publishes', function (): void {
    Event::fake();
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');

    Post::factory()->create(['status' => PostStatus::Scheduled, 'published_at' => now()->subHour()]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    Event::assertDispatched(PostPublished::class);
});
