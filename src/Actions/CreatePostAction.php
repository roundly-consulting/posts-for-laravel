<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Support\PostModel;

final class CreatePostAction
{
    public function execute(CreatePostData $data): Post
    {
        // Through the seam, never `new Post`: `posts.model` is a documented swap, and a
        // hard-coded class here creates the row as the PACKAGED model even when a host has
        // swapped it — same table, so the row looks identical and only the host's model
        // events going missing gives it away.
        $post = PostModel::new();
        $post->status = $data->status;
        $post->published_at = $data->publishedAt !== null
            ? CarbonImmutable::instance($data->publishedAt)
            : null;

        foreach ($data->translations as $translation) {
            $this->applyTranslation($post, $translation->locale, 'title', $translation->title);
            $this->applyTranslation($post, $translation->locale, 'slug', $translation->slug);
            $this->applyTranslation($post, $translation->locale, 'perex', $translation->perex);
            $this->applyTranslation($post, $translation->locale, 'content', $translation->content);
            $this->applyTranslation($post, $translation->locale, 'meta_title', $translation->metaTitle);
            $this->applyTranslation($post, $translation->locale, 'meta_description', $translation->metaDescription);
        }

        if ($data->authorType !== null && $data->authorId !== null) {
            $post->author_type = $data->authorType;
            $post->author_id = $data->authorId;
        }

        if ($data->seo !== null) {
            $post->seo = $data->seo->toBag();
        }

        $post->save();

        return $post;
    }

    private function applyTranslation(Post $post, string $locale, string $attribute, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $post->setTranslation($attribute, $locale, $value);
    }
}
