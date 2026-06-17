<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Tests\Support\UuidAuthorTestModel;

it('builds a uuid author_id column when configured for uuid keys', function (): void {
    expect(Schema::hasColumn(config('posts.tables.posts'), 'author_id'))->toBeTrue()
        ->and(Schema::getColumnType(config('posts.tables.posts'), 'author_id'))->not->toBe('integer');
});

it('authors posts from a uuid-keyed host model', function (): void {
    $author = UuidAuthorTestModel::create(['name' => 'Jane']);

    $post = Post::factory()->forAuthor($author)->create();

    expect($post->author)
        ->toBeInstanceOf(UuidAuthorTestModel::class)
        ->and((string) $author->getKey())->toBeString()
        ->and($author->posts)->toHaveCount(1);
});
