<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\MediaLibrary\Jobs\GenerateVariantsJob;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Listeners\WarmPostMediaVariants;
use RoundlyConsulting\Posts\Models\Post;

beforeEach(function (): void {
    Storage::fake('public');
});

it('registers a queued listener for the published event', function (): void {
    expect(new WarmPostMediaVariants)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
    expect(Event::getListeners(PostPublished::class))->not->toBeEmpty();
});

it('queues a variant job per media on publish', function (): void {
    $post = Post::factory()->create();
    $featured = $post->addMedia(UploadedFile::fake()->image('hero.jpg', 800, 600))->toMediaBucket($post->featuredBucket());
    $gallery = $post->addMedia(UploadedFile::fake()->image('g.jpg', 800, 600))->toMediaBucket($post->galleryBucket());

    Queue::fake();

    (new WarmPostMediaVariants)->handle(new PostPublished($post->id));

    Queue::assertPushed(GenerateVariantsJob::class, 2);

    foreach ([$featured->getKey(), $gallery->getKey()] as $mediaId) {
        Queue::assertPushed(
            GenerateVariantsJob::class,
            fn (GenerateVariantsJob $job): bool => $job->mediaId === $mediaId,
        );
    }
});

it('queues nothing when warming is disabled', function (): void {
    config()->set('posts.media.warm_on_publish', false);

    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('hero.jpg', 800, 600))->toMediaBucket($post->featuredBucket());

    Queue::fake();

    (new WarmPostMediaVariants)->handle(new PostPublished($post->id));

    Queue::assertNotPushed(GenerateVariantsJob::class);
});

it('queues nothing for a post without media', function (): void {
    $post = Post::factory()->create();

    Queue::fake();

    (new WarmPostMediaVariants)->handle(new PostPublished($post->id));

    Queue::assertNotPushed(GenerateVariantsJob::class);
});

/**
 * The unknown id must be well-typed for the configured key type (bigint by default). A uuid
 * string here is not "an unknown post" but a type error: SQLite swallowed it by affinity and
 * returned null, while Postgres rejects it outright. What this test means to prove is that a
 * *valid but absent* id warms nothing.
 */
it('ignores an unknown post id', function (): void {
    Queue::fake();

    (new WarmPostMediaVariants)->handle(new PostPublished(999_999_999));

    Queue::assertNotPushed(GenerateVariantsJob::class);
});

it('fails loudly on an unresolvable post model', function (): void {
    // A garbage `posts.model` used to be swallowed here, so a misconfigured host
    // silently stopped warming variants. The resolver now says so.
    config()->set('posts.model', 'Not\\A\\Real\\Model');

    Queue::fake();

    expect(fn () => (new WarmPostMediaVariants)->handle(new PostPublished('00000000-0000-0000-0000-000000000000')))
        ->toThrow(InvalidConfigurationException::class);

    Queue::assertNotPushed(GenerateVariantsJob::class);
});

it('queues nothing when the buckets declare no variants', function (): void {
    config()->set('posts.media.responsive_widths', []);

    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('hero.jpg', 800, 600))->toMediaBucket($post->featuredBucket());

    Queue::fake();

    (new WarmPostMediaVariants)->handle(new PostPublished($post->id));

    Queue::assertNotPushed(GenerateVariantsJob::class);
});
