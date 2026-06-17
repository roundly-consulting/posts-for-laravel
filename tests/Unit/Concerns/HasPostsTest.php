<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;

it('exposes the posts an author has written', function (): void {
    $author = AuthorTestModel::create(['name' => 'John']);

    Post::create(['author_id' => $author->getKey(), 'content' => 'This lake looks awesome.']);
    Post::create(['author_id' => $author->getKey(), 'content' => 'Today was a good day.']);

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
