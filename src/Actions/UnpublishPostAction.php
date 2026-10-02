<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostDrafted;
use RoundlyConsulting\Posts\Models\Post;

final readonly class UnpublishPostAction
{
    public function __construct(
        private TransitionPostAction $transition,
    ) {}

    /**
     * Move the post back to draft from any status, clearing its publish date, and dispatch
     * {@see PostDrafted}. A post that is already a draft is left as it is and fires nothing.
     */
    public function execute(Post $post): Post
    {
        if ($this->transition->execute($post, PostStatus::Draft, null)) {
            PostDrafted::dispatch($post->id);
        }

        return $post;
    }
}
