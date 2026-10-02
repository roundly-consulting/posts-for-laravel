<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use Carbon\CarbonInterface;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostScheduled;
use RoundlyConsulting\Posts\Exceptions\InvalidPostStatusTransitionException;
use RoundlyConsulting\Posts\Models\Post;

final readonly class SchedulePostAction
{
    public function __construct(
        private TransitionPostAction $transition,
    ) {}

    /**
     * Schedule the post for `$at` and dispatch {@see PostScheduled}. `posts:publish-scheduled`
     * (or `Posts::publishDue()`) publishes it once that time has passed.
     *
     * @throws InvalidPostStatusTransitionException when the post is archived
     */
    public function execute(Post $post, CarbonInterface $at): Post
    {
        // Scheduling always moves the date, so it always fires.
        $this->transition->execute($post, PostStatus::Scheduled, $at);

        PostScheduled::dispatch($post->id);

        return $post;
    }
}
