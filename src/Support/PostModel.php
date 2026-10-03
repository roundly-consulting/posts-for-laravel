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
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
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
        return ModelResolver::for('posts.model', Post::class);
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
