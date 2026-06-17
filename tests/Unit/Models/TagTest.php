<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;

it('auto-generates a translated slug from the name', function (): void {
    $tag = Tag::factory()->create(['name' => ['en' => 'Web Dev', 'sk' => 'Web Vyvoj']]);

    expect($tag->getTranslation('slug', 'en'))->toBe('web-dev')
        ->and($tag->getTranslation('slug', 'sk'))->toBe('web-vyvoj');
});

it('relates to many posts', function (): void {
    $tag = Tag::factory()->create();
    $tag->posts()->attach(Post::factory()->create());

    expect($tag->posts()->count())->toBe(1);
});

it('find-or-creates tags by name when syncing from strings', function (): void {
    Tag::factory()->create(['name' => ['en' => 'php']]);

    $post = Post::factory()->create();
    $post->syncTags(['php', 'laravel']);

    expect(Tag::query()->count())->toBe(2)
        ->and($post->tags()->count())->toBe(2);
});

it('does not duplicate existing tags on sync', function (): void {
    $post = Post::factory()->create();
    $post->syncTags(['php']);
    $post->syncTags(['php']);

    expect(Tag::query()->count())->toBe(1);
});

it('accepts tag models when syncing', function (): void {
    $tag = Tag::factory()->create(['name' => ['en' => 'eloquent']]);
    $post = Post::factory()->create();

    $post->syncTags([$tag]);

    expect($post->tags()->count())->toBe(1);
});

it('soft deletes tags', function (): void {
    $tag = Tag::factory()->create();
    $tag->delete();

    expect(Tag::query()->count())->toBe(0)
        ->and(Tag::withTrashed()->count())->toBe(1);
});

it('uses the configured table name', function (): void {
    expect((new Tag)->getTable())->toBe(config('posts.tables.tags'));
});
