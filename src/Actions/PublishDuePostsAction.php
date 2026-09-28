<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Support\PostModel;

final readonly class PublishDuePostsAction
{
    /**
     * Publish every scheduled post whose publish time has passed, keeping its scheduled time as
     * the publish date.
     *
     * Each post is published through its own `publish()`, which routes through the manager: a
     * host model that overrides `publish()` (`posts.model`) still runs its override, and a
     * faked manager records each one.
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
            $post->publish($post->published_at);
            $published++;
        }

        return $published;
    }
}
