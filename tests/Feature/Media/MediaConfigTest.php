<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Posts\Models\Post;

beforeEach(function (): void {
    Storage::fake('public');
});

it('stores post media on the configured disk', function (): void {
    config()->set('posts.media.disk', 'public');

    $post = Post::factory()->create();

    $media = $post->addMedia(UploadedFile::fake()->image('hero.jpg', 800, 600))
        ->toMediaBucket($post->featuredBucket());

    expect($media->disk)->toBe('public');
    expect($post->featuredImageUrl())->not->toBe('');
});

it('applies a custom responsive width ladder, ignoring invalid widths', function (): void {
    config()->set('posts.media.responsive_widths', [320, 0, -5, 'bad', 640]);

    $post = Post::factory()->create();

    $media = $post->addMedia(UploadedFile::fake()->image('hero.jpg', 800, 600))
        ->toMediaBucket($post->galleryBucket());

    expect($media->hasGeneratedVariant('responsive-320'))->toBeTrue();
    expect($media->hasGeneratedVariant('responsive-640'))->toBeTrue();
});
