<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\Models\Post;

final class CreatePostAction
{
    public function execute(CreatePostData $data): Post
    {
        $post = new Post;
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
