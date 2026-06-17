<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Posts\Actions\PublishPostAction;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Models\Post;

afterEach(fn () => CarbonImmutable::setTestNow());

it('publishes a post through the action', function (): void {
    CarbonImmutable::setTestNow('2026-03-03 15:00:00');

    $post = Post::factory()->draft()->create();

    $result = app(PublishPostAction::class)->execute($post);

    expect($result->status)->toBe(PostStatus::Published)
        ->and($result->published_at->toDateTimeString())->toBe('2026-03-03 15:00:00');
});
