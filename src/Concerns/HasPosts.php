<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Support\PostModel;

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
        return $this->morphMany(PostModel::class(), (string) config('posts.author.morph-name', 'author'));
    }
}
