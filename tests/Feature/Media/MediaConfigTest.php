<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
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

it('refuses a responsive width ladder with an invalid width (strict config)', function (mixed $widths): void {
    config()->set('posts.media.responsive_widths', $widths);

    expect(fn () => Post::factory()->create()->addMedia(UploadedFile::fake()->image('hero.jpg', 800, 600))
        ->toMediaBucket('gallery'))
        ->toThrow(InvalidConfigurationException::class, 'posts.media.responsive_widths');
})->with([
    'zero' => [[320, 0]],
    'negative' => [[320, -5]],
    'junk' => [[320, 'bad']],
    'a string' => ['320,640'],
]);

it('applies a custom responsive width ladder', function (): void {
    config()->set('posts.media.responsive_widths', [320, '640']);

    $post = Post::factory()->create();

    $media = $post->addMedia(UploadedFile::fake()->image('hero.jpg', 800, 600))
        ->toMediaBucket($post->galleryBucket());

    expect($media->hasGeneratedVariant('responsive-320'))->toBeTrue();
    expect($media->hasGeneratedVariant('responsive-640'))->toBeTrue();
});
