<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Concerns;

use Illuminate\Support\Str;
use Acme\Translatable\HasTranslations;

/**
 * Auto-generates per-locale slugs from the configured source attribute on save.
 *
 * Requires the model to also use Acme's HasTranslations and to list both the
 * source attribute and "slug" as translatable.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 * @mixin HasTranslations
 */
trait HasSluggableTranslations
{
    public static function bootHasSluggableTranslations(): void
    {
        static::saving(function (self $model): void {
            $model->generateSlugs();
        });
    }

    public function slugSource(): string
    {
        return (string) config('posts.slugs.source', 'title');
    }

    public function generateSlugs(): void
    {
        $separator = (string) config('posts.slugs.separator', '-');
        $unique = (bool) config('posts.slugs.unique', true);

        /** @var array<string, string> $titles */
        $titles = $this->getTranslations($this->slugSource());

        foreach ($titles as $locale => $title) {
            if ($title === '') {
                continue;
            }

            $existingSlug = $this->getTranslation('slug', $locale, false);

            if (is_string($existingSlug) && $existingSlug !== '') {
                continue;
            }

            $base = Str::slug($title, $separator);

            if ($base === '') {
                continue;
            }

            $slug = $unique
                ? $this->makeUniqueSlug($locale, $base, $separator)
                : $base;

            $this->setTranslation('slug', $locale, $slug);
        }
    }

    private function makeUniqueSlug(string $locale, string $base, string $separator): string
    {
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($locale, $slug)) {
            $slug = $base.$separator.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $locale, string $slug): bool
    {
        return $this->newQuery()
            ->whereKeyNot($this->getKey())
            ->where(function ($query) use ($locale, $slug): void {
                $query->getQuery()->where("slug->{$locale}", $slug);
            })
            ->exists();
    }
}
