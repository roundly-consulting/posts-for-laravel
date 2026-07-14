<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Tests\Support\UlidAuthorTestModel;

it('builds a string author_id column when configured for ulid keys', function (): void {
    expect(Schema::hasColumn(config('posts.tables.posts'), 'author_id'))->toBeTrue()
        ->and(Schema::getColumnType(config('posts.tables.posts'), 'author_id'))->not->toBe('integer');
});

it('authors posts from a ulid-keyed host model', function (): void {
    $author = UlidAuthorTestModel::create(['name' => 'Jane']);

    $post = Post::factory()->forAuthor($author)->create();

    expect($post->author)
        ->toBeInstanceOf(UlidAuthorTestModel::class)
        ->and((string) $author->getKey())->toHaveLength(26)
        ->and($author->posts)->toHaveCount(1);
});

it('round-trips the author relation through the morph key', function (): void {
    $author = UlidAuthorTestModel::create(['name' => 'Ada']);

    $post = Post::factory()->forAuthor($author)->create();

    expect($post->fresh()->author_id)->toBe((string) $author->getKey())
        ->and($post->fresh()->author_type)->toBe(UlidAuthorTestModel::class);
});
