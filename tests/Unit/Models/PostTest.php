<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;

it('uses the configured primary key, defaulting to an auto-incrementing bigint', function (): void {
    $post = Post::factory()->create();

    expect($post->getKeyName())->toBe('id')
        ->and($post->getKey())->toBeInt()
        ->and($post->getIncrementing())->toBeTrue();
});

it('casts the status to the PostStatus enum', function (): void {
    $post = Post::factory()->published()->create();

    expect($post->fresh()->status)->toBe(PostStatus::Published);
});

it('stores and reads translated content per locale', function (): void {
    $post = new Post;
    $post->setTranslation('title', 'en', 'Hello world');
    $post->setTranslation('title', 'sk', 'Ahoj svet');
    $post->setTranslation('content', 'en', '<p>Body</p>');
    $post->save();

    expect($post->getTranslation('title', 'en'))->toBe('Hello world')
        ->and($post->getTranslation('title', 'sk'))->toBe('Ahoj svet')
        ->and($post->getTranslation('content', 'en'))->toBe('<p>Body</p>');
});

it('falls back to the fallback locale for missing translations', function (): void {
    config()->set('translatable.fallback_locale', 'en');

    $post = Post::factory()->withTitles(['en' => 'Only English'])->create();

    expect($post->getTranslation('title', 'fr'))->toBe('Only English');
});

it('soft deletes posts', function (): void {
    $post = Post::factory()->create();
    $post->delete();

    expect(Post::query()->count())->toBe(0)
        ->and(Post::withTrashed()->count())->toBe(1);
});

it('resolves its author through a polymorphic relation', function (): void {
    $author = AuthorTestModel::create(['name' => 'John']);
    $post = Post::factory()->forAuthor($author)->create();

    expect($post->author)
        ->toBeInstanceOf(AuthorTestModel::class)
        ->name->toBe('John');
});

it('has no author when none is assigned', function (): void {
    expect(Post::factory()->create()->author)->toBeNull();
});

it('builds posts from each factory state', function (): void {
    expect(Post::factory()->draft()->create()->status)->toBe(PostStatus::Draft)
        ->and(Post::factory()->published()->create()->status)->toBe(PostStatus::Published)
        ->and(Post::factory()->scheduled()->create()->status)->toBe(PostStatus::Scheduled)
        ->and(Post::factory()->archived()->create()->status)->toBe(PostStatus::Archived);
});

it('uses the configured table name', function (): void {
    config()->set('posts.tables.posts', 'posts');

    expect((new Post)->getTable())->toBe('posts');
});
