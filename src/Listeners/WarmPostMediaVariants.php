<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use RoundlyConsulting\MediaLibrary\Jobs\GenerateVariantsJob;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Support\PostModel;

/**
 * On PostPublished, (re)warm the post's media variants so responsive derivatives are ready when
 * the post goes live: one queued {@see GenerateVariantsJob} per media across the featured, gallery
 * and content buckets. No-op when warming is disabled, the post is gone, or it carries no media.
 */
final class WarmPostMediaVariants implements ShouldQueue
{
    public function handle(PostPublished $event): void
    {
        if (! Config::boolean('posts.media.warm_on_publish', true)) {
            return;
        }

        $post = $this->resolvePost($event->postId);

        if (! $post instanceof Post) {
            return;
        }

        $buckets = [$post->featuredBucket(), $post->galleryBucket(), $post->contentBucket()];

        foreach ($buckets as $bucket) {
            foreach ($post->getMedia($bucket) as $media) {
                $this->warm($media);
            }
        }
    }

    private function warm(Media $media): void
    {
        $variantNames = array_map(
            static fn (object $variant): string => $variant->name,
            $media->resolveVariants(),
        );

        if ($variantNames === []) {
            return;
        }

        GenerateVariantsJob::dispatch((int) $media->getKey(), $variantNames);
    }

    private function resolvePost(int|string $postId): ?Post
    {
        return PostModel::query()->find($postId);
    }
}
