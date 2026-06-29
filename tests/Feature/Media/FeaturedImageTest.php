<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Posts\Models\Post;

beforeEach(function (): void {
    Storage::fake('public');
});

it('exposes the featured image and its url once attached', function (): void {
    $post = Post::factory()->create();

    $post->addMedia(UploadedFile::fake()->image('hero.jpg', 800, 600))
        ->toMediaBucket($post->featuredBucket());

    expect($post->featuredImage())->not->toBeNull();
    expect($post->featuredImageUrl())->not->toBe('');
});

it('keeps the featured bucket single-file by replacing the prior image', function (): void {
    $post = Post::factory()->create();

    $post->addMedia(UploadedFile::fake()->image('first.jpg', 400, 300))
        ->toMediaBucket($post->featuredBucket());

    $second = $post->addMedia(UploadedFile::fake()->image('second.jpg', 400, 300))
        ->toMediaBucket($post->featuredBucket());

    expect($post->getMedia($post->featuredBucket()))->toHaveCount(1);
    expect($post->featuredImage()?->getKey())->toBe($second->getKey());
});

it('returns an empty string for the featured url when none is set', function (): void {
    $post = Post::factory()->create();

    expect($post->featuredImageUrl())->toBe('');
});

it('returns the configured fallback url when the featured bucket is empty', function (): void {
    config()->set('posts.media.featured_fallback_url', 'https://cdn.test/placeholder.png');

    $post = Post::factory()->create();

    expect($post->featuredImageUrl())->toBe('https://cdn.test/placeholder.png');
});
