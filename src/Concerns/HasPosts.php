<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Posts\Models\Post;

/**
 * Add post authorship to any model (typically the application's User model),
 * regardless of whether it uses bigint or UUID primary keys.
 *
 * @mixin Model
 */
trait HasPosts
{
    /** @return MorphMany<Post, $this> */
    public function posts(): MorphMany
    {
        /** @var class-string<Post> $model */
        $model = config('posts.model', Post::class);

        return $this->morphMany($model, (string) config('posts.author.morph-name', 'author'));
    }
}
