<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Models\Post;

/**
 * A host post model with a global scope that hides every draft — the shape of a tenant or
 * visibility scope. The old slug check ran through `newQuery()`, so a row this scope hides
 * could never collide and the same slug was minted twice.
 */
final class PublishedOnlyPost extends Post
{
    protected static function booted(): void
    {
        self::addGlobalScope('published-only', static function (Builder $query): void {
            $query->where('status', PostStatus::Published);
        });
    }
}
