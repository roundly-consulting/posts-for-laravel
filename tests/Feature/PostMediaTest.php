<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Posts\Models\Post;

it('attaches an image to a post in the default collection', function (): void {
    Storage::fake('public');

    $post = Post::factory()->create();

    $post->addMedia(UploadedFile::fake()->image('lake.jpg'))
        ->toMediaCollection();

    $this->assertDatabaseHas('media', [
        'model_id' => $post->getKey(),
        'model_type' => Post::class,
        'collection_name' => 'default',
        'name' => 'lake',
        'file_name' => 'lake.jpg',
        'disk' => config('posts.disk'),
    ]);

    expect($post->getFirstMediaUrl())->not->toBe('');
});

it('registers a non-queued preview conversion when conversions are synchronous', function (): void {
    config()->set('posts.queue-file-conversions', false);

    $post = new Post;
    $post->registerMediaConversions();

    $preview = collect($post->mediaConversions)->firstWhere(fn ($conversion) => $conversion->getName() === 'preview');

    expect($preview)->not->toBeNull()
        ->and($preview->shouldBeQueued())->toBeFalse();
});

it('registers a queued preview conversion when conversions are queued', function (): void {
    config()->set('posts.queue-file-conversions', true);

    $post = new Post;
    $post->registerMediaConversions();

    $preview = collect($post->mediaConversions)->firstWhere(fn ($conversion) => $conversion->getName() === 'preview');

    expect($preview)->not->toBeNull()
        ->and($preview->shouldBeQueued())->toBeTrue();
});

it('uses the featured image as the og:image fallback', function (): void {
    Storage::fake('public');

    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection();

    expect($post->seo()->ogImage)->not->toBeNull();
});
