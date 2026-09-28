<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Exceptions\InvalidPostStatusTransitionException;
use RoundlyConsulting\Posts\Models\Post;

/**
 * Move a post to a status and persist it. An archived post may only go back to draft (or stay
 * archived); every other move is allowed. The lifecycle actions compose this and fire their own
 * event, so it is a building block and not on the facade.
 *
 * @internal
 */
final readonly class TransitionPostAction
{
    /**
     * @throws InvalidPostStatusTransitionException
     */
    public function execute(Post $post, PostStatus $status, ?CarbonInterface $publishedAt): Post
    {
        if ($post->status === PostStatus::Archived && $status !== PostStatus::Draft && $status !== PostStatus::Archived) {
            throw InvalidPostStatusTransitionException::between($post->status, $status);
        }

        $post->status = $status;
        $post->published_at = $publishedAt !== null ? CarbonImmutable::instance($publishedAt) : null;
        $post->save();

        return $post;
    }
}
