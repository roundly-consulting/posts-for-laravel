<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;

it('uses a uuid primary key', function (): void {
    $post = Post::create(['content' => 'Hello world.']);

    expect($post->getKeyName())->toBe('id')
        ->and($post->getKey())->toBeString()
        ->and($post->getIncrementing())->toBeFalse();
});

it('casts the visible flag to a boolean', function (): void {
    $post = Post::create(['content' => 'Hidden post', 'visible' => 0]);

    expect($post->fresh()->visible)->toBeFalse();
});

it('defaults visible to true', function (): void {
    $post = Post::create(['content' => 'Visible post']);

    expect($post->fresh()->visible)->toBeTrue();
});

it('belongs to its configured author model', function (): void {
    $author = AuthorTestModel::create(['name' => 'John']);

    $post = Post::create([
        'author_id' => $author->getKey(),
        'content' => 'This lake looks awesome.',
    ]);

    expect($post->author)
        ->toBeInstanceOf(AuthorTestModel::class)
        ->name->toBe('John');
});

it('builds posts from the factory', function (): void {
    $post = Post::factory()->create();

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->visible)->toBeTrue()
        ->and($post->content)->toBeString();
});

it('builds hidden posts via the factory state', function (): void {
    $post = Post::factory()->hidden()->create();

    expect($post->visible)->toBeFalse();
});
