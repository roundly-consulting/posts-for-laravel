<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Posts\Models\Category;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\Tests\Support\ReaderTestModel;

/**
 * The seam's uuid leg. A config-driven key type that only works on its default is not a
 * seam — the migrations and the models must agree on the same config value across all five
 * tables, or the package mints ids its own pivots cannot hold.
 */
it('mints uuid primary keys across posts, tags and categories', function (): void {
    $post = Post::factory()->create();

    expect($post->id)->toBeString()->toHaveLength(36)
        ->and($post->getKeyType())->toBe('string')
        ->and($post->getIncrementing())->toBeFalse()
        ->and($post->uniqueIds())->toBe(['id'])
        ->and(Tag::factory()->create()->id)->toBeString()->toHaveLength(36)
        ->and(Category::factory()->create()->id)->toBeString()->toHaveLength(36);
});

it('attaches tags and categories across the uuid pivots', function (): void {
    $post = Post::factory()->create();

    $post->tags()->attach(Tag::factory()->create());
    $post->categories()->attach(Category::factory()->create());

    expect($post->tags()->count())->toBe(1)
        ->and($post->categories()->count())->toBe(1);
});

it('nests uuid categories through the self-referential parent key', function (): void {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent->id)->toBe($parent->id);
});

/**
 * The negative control, and the documented cost of the seam in executable form.
 *
 * A green key-type test proves nothing until you have watched the engine reject the broken
 * shape. `uuid` is supported — but only for a host whose morph targets are ALL uuid-keyed.
 * `likes.likeable_id` is a raw `morphs()` column, i.e. an unsigned bigint, and it cannot
 * hold this id. That is exactly the failure that shipped as the default, and it is why the
 * default is now bigint.
 *
 * Postgres-only on purpose: SQLite's type affinity stores the uuid string in an INTEGER
 * column without complaint, so this test CANNOT fail there. That silent acceptance is what
 * hid the bug fleet-wide, and a skip here is the honest report of it.
 */
it('cannot be liked while likes keys its morph column to bigint', function (): void {
    $post = Post::factory()->create();
    $user = ReaderTestModel::create(['name' => 'Ada']);

    expect(fn () => Likes::actor($user)->like($post))
        ->toThrow(QueryException::class, 'invalid input syntax for type bigint');
})->skip(
    fn (): bool => config('database.connections.testing.driver') !== 'pgsql',
    'sqlite type affinity accepts the uuid silently — only a strict engine detects this',
);

it('filters uuid posts by a category or tag instance and by slug', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'Guides']]);
    $tag = Tag::factory()->create(['name' => ['en' => 'Eloquent']]);
    $post = Post::factory()->create();
    $post->categories()->attach($category);
    $post->tags()->attach($tag);
    Post::factory()->create();

    expect(Post::query()->inCategory($category)->pluck('id')->all())->toBe([$post->id])
        ->and(Post::query()->inCategory('guides')->pluck('id')->all())->toBe([$post->id])
        ->and(Post::query()->withTag($tag)->count())->toBe(1)
        ->and(Post::query()->withTag('eloquent')->count())->toBe(1);
});
