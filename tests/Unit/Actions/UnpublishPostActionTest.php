<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Posts\Actions\UnpublishPostAction;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostDrafted;
use RoundlyConsulting\Posts\Models\Post;

it('moves a post back to draft, clears its date and dispatches PostDrafted', function (PostStatus $from): void {
    Event::fake([PostDrafted::class]);

    $post = Post::factory()->create(['status' => $from, 'published_at' => now()->subDay()]);

    app(UnpublishPostAction::class)->execute($post);

    $fresh = $post->fresh();

    expect($fresh?->status)->toBe(PostStatus::Draft)
        ->and($fresh?->published_at)->toBeNull();

    Event::assertDispatched(PostDrafted::class, fn (PostDrafted $event): bool => $event->postId === $post->id);
})->with([
    'published' => PostStatus::Published,
    'scheduled' => PostStatus::Scheduled,
    'archived' => PostStatus::Archived,
]);
