<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Posts\Models\Post;

/**
 * The single seam through which the package resolves the host-configured post
 * model (`posts.model`).
 *
 * Wraps the toolkit's {@see ModelResolver} and narrows its `class-string<Model>`
 * to `class-string<Post>`, so every call site gets the post API without an inline
 * `@var` override. A configured class that is a real Eloquent model but not a
 * `Post` falls back to the packaged model — the tolerance the listeners already
 * had — while a value that is not a model at all throws the toolkit's
 * `InvalidConfigurationException`.
 *
 * Hosts query through `Posts::query()` / `Posts::findBySlug()` instead.
 *
 * @internal
 */
final class PostModel
{
    /** @return class-string<Post> */
    public static function class(): string
    {
        $model = ModelResolver::for('posts.model', Post::class);

        return is_a($model, Post::class, true) ? $model : Post::class;
    }

    public static function new(): Post
    {
        $class = self::class();

        return new $class;
    }

    /** @return Builder<Post> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
