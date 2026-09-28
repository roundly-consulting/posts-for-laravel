<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostArchived;
use RoundlyConsulting\Posts\Models\Post;

final readonly class ArchivePostAction
{
    public function __construct(
        private TransitionPostAction $transition,
    ) {}

    /**
     * Archive the post, keeping its publish date, and dispatch {@see PostArchived}. An archived
     * post can only be moved back to draft.
     */
    public function execute(Post $post): Post
    {
        $this->transition->execute($post, PostStatus::Archived, $post->published_at);

        PostArchived::dispatch($post->id);

        return $post;
    }
}
