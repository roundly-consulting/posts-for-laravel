<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;

final readonly class SyncPostTagsAction
{
    /**
     * Sync the post's tags. A `Tag` is used as is; a string is matched against the tag names in
     * the current locale and created when no tag has that name. Tags not listed are detached.
     *
     * @param  iterable<int, string|Tag>  $tags
     */
    public function execute(Post $post, iterable $tags): Post
    {
        $locale = app()->getLocale();
        $ids = [];

        foreach ($tags as $tag) {
            if ($tag instanceof Tag) {
                $ids[] = $tag->getKey();

                continue;
            }

            $existing = Tag::query()
                ->where(function (Builder $query) use ($locale, $tag): void {
                    $query->getQuery()->where("name->{$locale}", $tag);
                })
                ->first();

            $ids[] = $existing?->getKey() ?? Tag::create([
                'name' => [$locale => $tag],
            ])->getKey();
        }

        $post->tags()->sync($ids);

        return $post;
    }
}
