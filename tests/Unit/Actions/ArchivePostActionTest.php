<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Posts\Actions\ArchivePostAction;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostArchived;
use RoundlyConsulting\Posts\Models\Post;

it('archives a post, keeps its publish date and dispatches PostArchived', function (): void {
    Event::fake([PostArchived::class]);

    $post = Post::factory()->published()->create();
    $publishedAt = $post->published_at;

    app(ArchivePostAction::class)->execute($post);

    $fresh = $post->fresh();

    expect($fresh?->status)->toBe(PostStatus::Archived)
        ->and($fresh?->published_at?->equalTo($publishedAt))->toBeTrue();

    Event::assertDispatched(PostArchived::class, fn (PostArchived $event): bool => $event->postId === $post->id);
});

it('archives an already archived post again', function (): void {
    $post = Post::factory()->archived()->create();

    expect(app(ArchivePostAction::class)->execute($post)->status)->toBe(PostStatus::Archived);
});
