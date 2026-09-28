<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Events\PostScheduled;
use RoundlyConsulting\Posts\Exceptions\InvalidPostStatusTransitionException;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Support\PostHydrator;

final readonly class CreatePostAction
{
    /**
     * Create the post as the configured `posts.model`. A post created published (dated now when
     * no date is given) dispatches {@see PostPublished}; one created scheduled dispatches
     * {@see PostScheduled}.
     *
     * @throws InvalidPostStatusTransitionException when a scheduled post has no publish date
     */
    public function execute(CreatePostData $data): Post
    {
        $post = PostHydrator::hydrate($data);
        $post->save();

        match ($post->status) {
            PostStatus::Published => PostPublished::dispatch($post->id),
            PostStatus::Scheduled => PostScheduled::dispatch($post->id),
            default => null,
        };

        return $post;
    }
}
