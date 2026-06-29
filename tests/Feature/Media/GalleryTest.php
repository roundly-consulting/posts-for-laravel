<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Posts\Models\Post;

beforeEach(function (): void {
    Storage::fake('public');
});

it('retains every gallery image in attachment order', function (): void {
    $post = Post::factory()->create();

    $first = $post->addMedia(UploadedFile::fake()->image('a.jpg', 400, 300))->toMediaBucket($post->galleryBucket());
    $second = $post->addMedia(UploadedFile::fake()->image('b.jpg', 400, 300))->toMediaBucket($post->galleryBucket());
    $third = $post->addMedia(UploadedFile::fake()->image('c.jpg', 400, 300))->toMediaBucket($post->galleryBucket());

    $gallery = $post->galleryImages();

    expect($gallery)->toHaveCount(3);
    expect($gallery->pluck('id')->all())->toBe([$first->id, $second->id, $third->id]);
});

it('maps gallery images to their urls', function (): void {
    $post = Post::factory()->create();

    $post->addMedia(UploadedFile::fake()->image('a.jpg', 400, 300))->toMediaBucket($post->galleryBucket());
    $post->addMedia(UploadedFile::fake()->image('b.jpg', 400, 300))->toMediaBucket($post->galleryBucket());

    $urls = $post->galleryImageUrls();

    expect($urls)->toHaveCount(2);
    expect($urls)->each->not->toBe('');
});

it('returns empty results for an empty gallery', function (): void {
    $post = Post::factory()->create();

    expect($post->galleryImages())->toHaveCount(0);
    expect($post->galleryImageUrls())->toBe([]);
});
