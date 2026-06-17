<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Models\Post;

it('auto-generates a slug per locale from the title', function (): void {
    $post = Post::factory()->withTitles(['en' => 'Hello World', 'sk' => 'Ahoj Svet'])->create();

    expect($post->getTranslation('slug', 'en'))->toBe('hello-world')
        ->and($post->getTranslation('slug', 'sk'))->toBe('ahoj-svet');
});

it('suffixes colliding slugs to keep them unique', function (): void {
    Post::factory()->withTitles(['en' => 'Hello World'])->create();
    $second = Post::factory()->withTitles(['en' => 'Hello World'])->create();
    $third = Post::factory()->withTitles(['en' => 'Hello World'])->create();

    expect($second->getTranslation('slug', 'en'))->toBe('hello-world-2')
        ->and($third->getTranslation('slug', 'en'))->toBe('hello-world-3');
});

it('respects a manually provided slug', function (): void {
    $post = new Post;
    $post->setTranslation('title', 'en', 'Hello World');
    $post->setTranslation('slug', 'en', 'custom-slug');
    $post->save();

    expect($post->getTranslation('slug', 'en'))->toBe('custom-slug');
});

it('does not enforce uniqueness when disabled', function (): void {
    config()->set('posts.slugs.unique', false);

    Post::factory()->withTitles(['en' => 'Hello World'])->create();
    $second = Post::factory()->withTitles(['en' => 'Hello World'])->create();

    expect($second->getTranslation('slug', 'en'))->toBe('hello-world');
});

it('skips slug generation for empty titles', function (): void {
    $post = Post::factory()->create(['title' => ['en' => '']]);

    expect($post->getTranslation('slug', 'en', false))->toBeIn(['', null]);
});

it('skips slug generation when the title slugifies to an empty string', function (): void {
    $post = Post::factory()->create(['title' => ['en' => '###']]);

    expect($post->getTranslation('slug', 'en', false))->toBeIn(['', null]);
});

it('skips empty locales while slugging the populated ones', function (): void {
    $post = Post::factory()->create(['title' => ['en' => 'Hello', 'sk' => '']]);

    expect($post->getTranslation('slug', 'en'))->toBe('hello')
        ->and($post->getTranslation('slug', 'sk', false))->toBeIn(['', null]);
});
