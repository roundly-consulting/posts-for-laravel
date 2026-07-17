<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Posts\Models\Category;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\Tests\Support\ReaderTestModel;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Facades\Reports;

/**
 * The default, and the package's central regression.
 *
 * `posts.id` shipped as an unconditional `uuid` while `likes.likeable_id` — a raw
 * `$table->morphs('likeable')` — is an unsigned bigint. A post therefore could not be liked
 * at all on PostgreSQL:
 *
 *     SQLSTATE[22P02]: invalid input syntax for type bigint: "019f6f33-22b8-737f-a581-…"
 *
 * SQLite hid it fleet-wide by type affinity, which is why these run on the real-engine leg
 * too. Note the bug needed no foreign key to exist: morph columns carry none.
 */
it('defaults to an auto-incrementing bigint primary key', function (): void {
    $post = Post::factory()->create();

    expect($post->id)->toBeInt()
        ->and($post->getKeyType())->toBe('int')
        ->and($post->getIncrementing())->toBeTrue()
        ->and($post->uniqueIds())->toBe([]);
});

it('defaults tags and categories to bigint primary keys too', function (): void {
    expect(Tag::factory()->create()->id)->toBeInt()
        ->and(Category::factory()->create()->id)->toBeInt();
});

it('emits integer id columns across every posts table', function (): void {
    $integerish = ['integer', 'bigint', 'int8'];

    expect(Schema::getColumnType('posts', 'id'))->toBeIn($integerish)
        ->and(Schema::getColumnType('post_tags', 'id'))->toBeIn($integerish)
        ->and(Schema::getColumnType('post_categories', 'id'))->toBeIn($integerish)
        ->and(Schema::getColumnType('post_categories', 'parent_id'))->toBeIn($integerish)
        ->and(Schema::getColumnType('post_tag', 'post_id'))->toBeIn($integerish)
        ->and(Schema::getColumnType('post_tag', 'tag_id'))->toBeIn($integerish)
        ->and(Schema::getColumnType('category_post', 'post_id'))->toBeIn($integerish)
        ->and(Schema::getColumnType('category_post', 'category_id'))->toBeIn($integerish);
});

/**
 * THE vector. This is the exact assertion that was watched failing on a real Postgres server
 * before a line of the fix was written.
 */
it('lets a post be liked — the vector that was broken by default', function (): void {
    $post = Post::factory()->create();
    $user = ReaderTestModel::create(['name' => 'Ada']);

    Likes::actor($user)->like($post);

    expect($post->likesCount())->toBe(1)
        ->and($post->isLikedBy($user))->toBeTrue();
});

it('lets a post be reported — the same shape through reports', function (): void {
    $post = Post::factory()->create();
    $user = ReaderTestModel::create(['name' => 'Ada']);

    $report = Reports::report($post)->by($user)->for(Reason::Spam)->create();

    expect($report->reported()->is($post))->toBeTrue()
        ->and($post->hasBeenReported())->toBeTrue();
});

it('attaches tags and categories across the bigint pivots', function (): void {
    $post = Post::factory()->create();
    $tag = Tag::factory()->create();
    $category = Category::factory()->create();

    $post->tags()->attach($tag);
    $post->categories()->attach($category);

    expect($post->tags()->count())->toBe(1)
        ->and($post->categories()->count())->toBe(1);
});

it('nests categories through the self-referential parent key', function (): void {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent->id)->toBe($parent->id)
        ->and($parent->children()->count())->toBe(1);
});
