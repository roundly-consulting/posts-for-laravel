<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Support;

use Carbon\CarbonInterface;
use RoundlyConsulting\Posts\Models\Post;

/**
 * Builds a schema.org BlogPosting/Article structured-data array for a post.
 */
final class JsonLdBuilder
{
    /** @return array<string, mixed> */
    public function build(Post $post): array
    {
        $locale = app()->getLocale();
        $seo = $post->seo();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => (string) config('posts.json-ld.type', 'BlogPosting'),
            'headline' => $post->getTranslation('title', $locale, false) ?: '',
            'description' => $seo->metaDescription ?? '',
        ];

        if (is_string($seo->ogImage) && $seo->ogImage !== '') {
            $data['image'] = $seo->ogImage;
        }

        if ($post->published_at instanceof CarbonInterface) {
            $data['datePublished'] = $post->published_at->toIso8601String();
        }

        if ($post->updated_at instanceof CarbonInterface) {
            $data['dateModified'] = $post->updated_at->toIso8601String();
        }

        $authorName = $this->authorName($post);

        if ($authorName !== null) {
            $data['author'] = [
                '@type' => 'Person',
                'name' => $authorName,
            ];
        }

        $publisher = $this->publisher();

        if ($publisher !== null) {
            $data['publisher'] = $publisher;
        }

        if ($seo->canonical !== null) {
            $data['mainEntityOfPage'] = [
                '@type' => 'WebPage',
                '@id' => $seo->canonical,
            ];
        }

        $keywords = $post->tags
            ->map(fn ($tag): string => $tag->getTranslation('name', $locale, false))
            ->filter(fn (string $name): bool => $name !== '')
            ->values();

        if ($keywords->isNotEmpty()) {
            $data['keywords'] = $keywords->all();
        }

        $sections = $post->categories
            ->map(fn ($category): string => $category->getTranslation('name', $locale, false))
            ->filter(fn (string $name): bool => $name !== '')
            ->values();

        if ($sections->isNotEmpty()) {
            $data['articleSection'] = $sections->all();
        }

        return $data;
    }

    private function authorName(Post $post): ?string
    {
        $author = $post->author;

        if ($author === null) {
            return null;
        }

        $attribute = (string) config('posts.json-ld.author-attribute', 'name');
        $value = $author->getAttribute($attribute);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, mixed>|null */
    private function publisher(): ?array
    {
        $name = config('posts.json-ld.publisher.name');

        if (! is_string($name) || $name === '') {
            return null;
        }

        $publisher = [
            '@type' => 'Organization',
            'name' => $name,
        ];

        $logo = config('posts.json-ld.publisher.logo');

        if (is_string($logo) && $logo !== '') {
            $publisher['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo,
            ];
        }

        return $publisher;
    }
}
