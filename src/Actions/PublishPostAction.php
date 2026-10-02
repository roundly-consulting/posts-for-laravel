<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use Carbon\CarbonInterface;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Exceptions\InvalidPostStatusTransitionException;
use RoundlyConsulting\Posts\Models\Post;

final readonly class PublishPostAction
{
    public function __construct(
        private TransitionPostAction $transition,
    ) {}

    /**
     * Publish the post at `$at` (now when omitted) and dispatch {@see PostPublished}. A post that
     * is already published is left as it is — its date is kept and no event fires — so a
     * repeated call, or a second `publishDue()` run racing the first, never re-dates it.
     *
     * @throws InvalidPostStatusTransitionException when the post is archived
     */
    public function execute(Post $post, ?CarbonInterface $at = null): Post
    {
        if ($this->transition->execute($post, PostStatus::Published, $at ?? now())) {
            PostPublished::dispatch($post->id);
        }

        return $post;
    }
}
