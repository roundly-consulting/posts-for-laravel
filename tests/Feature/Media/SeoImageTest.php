<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Post;

beforeEach(function (): void {
    Storage::fake('public');
});

it('falls back the og image to the featured image when none is set', function (): void {
    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('hero.jpg', 800, 600))->toMediaBucket($post->featuredBucket());

    $expected = $post->featuredImageUrl();

    expect($post->seo()->ogImage)->toBe($expected);
    expect($post->toJsonLd()['image'])->toBe($expected);
});

it('does not override an explicit og image with the featured image', function (): void {
    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('hero.jpg', 800, 600))->toMediaBucket($post->featuredBucket());

    $post->setSeo(new SeoData(ogImage: 'https://cdn.test/custom.png'));

    expect($post->seo()->ogImage)->toBe('https://cdn.test/custom.png');
});

it('does not fall back when the og image toggle is off', function (): void {
    config()->set('posts.media.seo_og_image', false);

    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('hero.jpg', 800, 600))->toMediaBucket($post->featuredBucket());

    expect($post->seo()->ogImage)->toBeNull();
});

it('emits no image when there is neither a featured image nor an override', function (): void {
    $post = Post::factory()->create();

    expect($post->seo()->ogImage)->toBeNull();
    expect($post->toJsonLd())->not->toHaveKey('image');
});
