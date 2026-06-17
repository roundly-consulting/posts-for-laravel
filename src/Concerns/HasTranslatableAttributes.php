<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Concerns;

/**
 * A small, self-contained replacement for the slice of translatable-attribute
 * behaviour the package relies on, with no third-party dependency.
 *
 * Each translatable attribute is stored as a `json` column cast to `array`, holding
 * one map of locale => value. The model declares which attributes are translatable
 * through a public `$translatable` array.
 *
 * @property list<string> $translatable
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasTranslatableAttributes
{
    /**
     * Read a single locale's value for a translatable attribute.
     *
     * When $useFallbackLocale is true and the requested locale has no value, the
     * application fallback locale is tried, then the first available translation.
     * Returns an empty string when nothing is found.
     */
    public function getTranslation(string $key, string $locale, bool $useFallbackLocale = true): string
    {
        $translations = $this->getTranslations($key);

        $value = $translations[$locale] ?? '';

        if ($value !== '') {
            return $value;
        }

        if (! $useFallbackLocale) {
            return $value;
        }

        $fallbackLocale = $this->translatableFallbackLocale();

        if ($fallbackLocale !== null && $fallbackLocale !== $locale) {
            $fallbackValue = $translations[$fallbackLocale] ?? '';

            if ($fallbackValue !== '') {
                return $fallbackValue;
            }
        }

        foreach ($translations as $translation) {
            if ($translation !== '') {
                return $translation;
            }
        }

        return $value;
    }

    /**
     * Read the full locale => value map for a translatable attribute.
     *
     * @return array<string, string>
     */
    public function getTranslations(string $key): array
    {
        $raw = $this->getAttributeValue($key);

        if (! is_array($raw)) {
            return [];
        }

        $translations = [];

        foreach ($raw as $locale => $value) {
            if (is_string($value)) {
                $translations[(string) $locale] = $value;
            }
        }

        return $translations;
    }

    /**
     * Set a single locale's value for a translatable attribute.
     */
    public function setTranslation(string $key, string $locale, string $value): static
    {
        $translations = $this->getTranslations($key);
        $translations[$locale] = $value;

        $this->setAttribute($key, $translations);

        return $this;
    }

    /**
     * Convenience accessor: the value for the given (or current) locale, with fallback.
     */
    public function translate(string $key, ?string $locale = null): string
    {
        return $this->getTranslation($key, $locale ?? app()->getLocale());
    }

    /**
     * Resolve a translatable attribute accessed directly (e.g. $post->title) to the
     * current-locale value with fallback, while leaving non-translatable attributes
     * to Eloquent's default handling.
     */
    public function getAttribute($key): mixed
    {
        if ($key !== '' && $this->isTranslatableAttribute($key) && ! $this->hasGetMutator($key) && ! $this->hasAttributeMutator($key)) {
            return $this->getTranslation($key, app()->getLocale());
        }

        return parent::getAttribute($key);
    }

    public function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->translatableAttributes(), true);
    }

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return $this->translatable;
    }

    private function translatableFallbackLocale(): ?string
    {
        $configured = config('translatable.fallback_locale')
            ?? config('posts.locales.fallback')
            ?? config('app.fallback_locale');

        return is_string($configured) && $configured !== '' ? $configured : null;
    }
}
