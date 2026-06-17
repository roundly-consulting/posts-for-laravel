<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;

it('exposes the posts an author has written via a morph relation', function (): void {
    $author = AuthorTestModel::create(['name' => 'John']);

    Post::factory()->forAuthor($author)->create();
    Post::factory()->forAuthor($author)->create();
    Post::factory()->create();

    expect($author->posts)
        ->toBeCollection()
        ->toHaveCount(2)
        ->first()
        ->toBeInstanceOf(Post::class);
});

it('uses the configured post model for the relationship', function (): void {
    config()->set('posts.model', Post::class);

    $author = AuthorTestModel::create(['name' => 'Jane']);

    expect($author->posts()->getRelated())->toBeInstanceOf(Post::class);
});
