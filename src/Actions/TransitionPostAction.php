<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Exceptions\InvalidPostStatusTransitionException;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Support\StoredPostState;

/**
 * Move a post to a status and persist it. An archived post may only go back to draft (or stay
 * archived); every other move is allowed. The lifecycle actions compose this and fire their own
 * event, so it is a building block and not on the facade.
 *
 * The decision is made against the STORED status, read under a row lock in the same transaction
 * as the write, so a stale in-memory copy can neither publish a post a moderator archived
 * meanwhile nor publish one twice. Moving a post to the status it already has is a no-op —
 * except Scheduled, where it moves the date.
 *
 * @internal
 */
final readonly class TransitionPostAction
{
    /**
     * @param  bool  $keepDate  keep the stored publish date instead of writing `$publishedAt`
     * @return bool whether the post moved — false when it already had `$status`: nothing is
     *              written, its date is kept, and the caller fires no event
     *
     * @throws InvalidPostStatusTransitionException
     */
    public function execute(Post $post, PostStatus $status, ?CarbonInterface $publishedAt, bool $keepDate = false): bool
    {
        return $post->getConnection()->transaction(static function () use ($post, $status, $publishedAt, $keepDate): bool {
            StoredPostState::lock($post);

            if ($post->status === PostStatus::Archived && $status !== PostStatus::Draft && $status !== PostStatus::Archived) {
                throw InvalidPostStatusTransitionException::between($post->status, $status);
            }

            if ($post->status === $status && $status !== PostStatus::Scheduled) {
                return false;
            }

            $post->status = $status;

            if (! $keepDate) {
                $post->published_at = $publishedAt !== null ? CarbonImmutable::instance($publishedAt) : null;
            }

            $post->save();

            return true;
        });
    }
}
