<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Support;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Exceptions\InvalidPostStatusTransitionException;
use RoundlyConsulting\Posts\Models\Post;

/**
 * Builds the unsaved post a {@see CreatePostData} describes, so the create action and the fake
 * return the same shape.
 *
 * @internal
 */
final class PostHydrator
{
    /**
     * @throws InvalidPostStatusTransitionException when a scheduled post has no publish date
     */
    public static function hydrate(CreatePostData $data): Post
    {
        if ($data->status === PostStatus::Scheduled && $data->publishedAt === null) {
            throw InvalidPostStatusTransitionException::scheduledWithoutDate();
        }

        // Through the seam, never `new Post`: `posts.model` is a documented swap, and a
        // hard-coded class here creates the row as the PACKAGED model even when a host has
        // swapped it — same table, so the row looks identical and only the host's model
        // events going missing gives it away.
        $post = PostModel::new();
        $post->status = $data->status;
        $post->published_at = match (true) {
            $data->publishedAt !== null => CarbonImmutable::instance($data->publishedAt),
            // A published post with no date would never match `published()` (published_at <= now).
            $data->status === PostStatus::Published => CarbonImmutable::instance(now()),
            default => null,
        };

        // SEO first: its meta title/description land on the current locale, and an explicit
        // per-locale value from the translations below wins over it.
        if ($data->seo !== null) {
            $post->setSeo($data->seo);
        }

        foreach ($data->translations as $translation) {
            self::apply($post, $translation->locale, 'title', $translation->title);
            self::apply($post, $translation->locale, 'slug', $translation->slug);
            self::apply($post, $translation->locale, 'perex', $translation->perex);
            self::apply($post, $translation->locale, 'content', $translation->content);
            self::apply($post, $translation->locale, 'meta_title', $translation->metaTitle);
            self::apply($post, $translation->locale, 'meta_description', $translation->metaDescription);
        }

        if ($data->authorType !== null && $data->authorId !== null) {
            $post->author_type = $data->authorType;
            $post->author_id = $data->authorId;
        }

        return $post;
    }

    private static function apply(Post $post, string $locale, string $attribute, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $post->setTranslation($attribute, $locale, $value);
    }
}
