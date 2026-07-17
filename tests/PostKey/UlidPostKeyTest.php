<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Models\Category;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;

/** The seam's ulid leg. @see UuidPostKeyTest */
it('mints ulid primary keys across posts, tags and categories', function (): void {
    $post = Post::factory()->create();

    expect($post->id)->toBeString()->toHaveLength(26)
        ->and($post->getKeyType())->toBe('string')
        ->and($post->getIncrementing())->toBeFalse()
        ->and($post->uniqueIds())->toBe(['id'])
        ->and(Tag::factory()->create()->id)->toBeString()->toHaveLength(26)
        ->and(Category::factory()->create()->id)->toBeString()->toHaveLength(26);
});

it('attaches tags and categories across the ulid pivots', function (): void {
    $post = Post::factory()->create();

    $post->tags()->attach(Tag::factory()->create());
    $post->categories()->attach(Category::factory()->create());

    expect($post->tags()->count())->toBe(1)
        ->and($post->categories()->count())->toBe(1);
});
