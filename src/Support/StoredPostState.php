<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Posts\Models\Post;

/**
 * Re-reads a post's stored status and publish date under a row lock — inside the caller's
 * transaction — and copies them onto the in-memory model. A lifecycle decision then never rests
 * on a copy loaded before another worker, a second `publishDue()` run or a moderator changed the
 * row: whoever takes the lock second sees the first one's write.
 *
 * @internal
 */
final class StoredPostState
{
    /**
     * @param  (Closure(Builder<Post>): mixed)|null  $where  conditions the stored row must still meet
     * @return bool false — leaving the model untouched — when the row is gone or no longer meets
     *              `$where`; true for an unsaved post, which has no stored state to read
     */
    public static function lock(Post $post, ?Closure $where = null): bool
    {
        if (! $post->exists) {
            return true;
        }

        $query = $post->newQueryWithoutScopes()->whereKey($post->getKey());

        if ($where !== null) {
            $where($query);
        }

        $stored = $query->lockForUpdate()->first();

        if (! $stored instanceof Post) {
            return false;
        }

        $post->setRawAttributes(array_replace($post->getAttributes(), [
            'status' => $stored->getRawOriginal('status'),
            'published_at' => $stored->getRawOriginal('published_at'),
        ]));
        $post->syncOriginalAttributes(['status', 'published_at']);

        return true;
    }
}
