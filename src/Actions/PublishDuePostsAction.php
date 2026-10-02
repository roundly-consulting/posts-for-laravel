<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Support\PostModel;
use RoundlyConsulting\Posts\Support\StoredPostState;

final readonly class PublishDuePostsAction
{
    /**
     * Publish every scheduled post whose publish time has passed, keeping its scheduled time as
     * the publish date.
     *
     * Each post is claimed first: its row is locked and re-checked — still scheduled, still due —
     * in a transaction that also holds the publish, so two overlapping runs (or a run racing a
     * moderator) publish each post at most once and never publish one that was archived,
     * drafted or rescheduled after this run listed it. A claimed post is published through its
     * own `publish()`, which routes through the manager: a host model that overrides `publish()`
     * (`posts.model`) still runs its override, and a faked manager records each one.
     *
     * @return int the number of posts published
     */
    public function execute(): int
    {
        $published = 0;

        $due = PostModel::query()
            ->where('status', PostStatus::Scheduled)
            ->where('published_at', '<=', now())
            ->lazyById();

        foreach ($due as $post) {
            $published += $post->getConnection()->transaction(static function () use ($post): int {
                $claimed = StoredPostState::lock($post, static function (Builder $query): void {
                    $query->where('status', PostStatus::Scheduled)->where('published_at', '<=', now());
                });

                if (! $claimed) {
                    return 0;
                }

                $post->publish($post->published_at);

                return 1;
            });
        }

        return $published;
    }
}
