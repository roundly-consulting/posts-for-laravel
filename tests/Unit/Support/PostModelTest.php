<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Support\PostModel;
use RoundlyConsulting\Posts\Tests\Support\CustomPost;
use RoundlyConsulting\Posts\Tests\Support\NotAPost;

it('resolves the packaged model by default', function (): void {
    expect(PostModel::class())->toBe(Post::class)
        ->and(PostModel::new())->toBeInstanceOf(Post::class)
        ->and(PostModel::query()->getModel())->toBeInstanceOf(Post::class);
});

it('resolves a configured host subclass', function (): void {
    config()->set('posts.model', CustomPost::class);

    expect(PostModel::class())->toBe(CustomPost::class);
});

it('falls back to the packaged model for an eloquent model that is not a post', function (): void {
    config()->set('posts.model', NotAPost::class);

    expect(PostModel::class())->toBe(Post::class);
});

it('throws on a configured class that is not a model at all', function (): void {
    config()->set('posts.model', 'Not\\A\\Real\\Model');

    expect(fn (): string => PostModel::class())->toThrow(InvalidConfigurationException::class);
});

it('falls back to the packaged model when the key is unset', function (): void {
    config()->set('posts.model', null);

    expect(PostModel::class())->toBe(Post::class);
});
