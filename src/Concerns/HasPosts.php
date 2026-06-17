<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RoundlyConsulting\Posts\Models\Post;

/**
 * Add post authorship to any model (typically the application's User model).
 *
 * @mixin Model
 */
trait HasPosts
{
    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        /** @var class-string<Post> $model */
        $model = config('posts.model');

        return $this->hasMany($model, 'author_id');
    }
}
