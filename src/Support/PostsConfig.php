<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Strict readers for the package's non-boolean settings.
 *
 * A default applies only when the key is absent (null). Anything present but unusable — a blank
 * or non-string table name, a `BlogPostng` schema type, an `archvie` moderation action, a junk
 * width — throws {@see InvalidConfigurationException} naming the key, instead of being cast to
 * `''` / `Array`, passed through, or replaced by a default.
 *
 * @internal
 */
final class PostsConfig
{
    public static function postsTable(): string
    {
        return self::string('posts.tables.posts', 'posts');
    }

    public static function categoriesTable(): string
    {
        return self::string('posts.tables.categories', 'post_categories');
    }

    public static function categoryPostTable(): string
    {
        return self::string('posts.tables.category_post', 'category_post');
    }

    public static function tagsTable(): string
    {
        return self::string('posts.tables.tags', 'post_tags');
    }

    public static function tagPostTable(): string
    {
        return self::string('posts.tables.tag_post', 'post_tag');
    }

    public static function authorMorphName(): string
    {
        return self::string('posts.author.morph-name', 'author');
    }

    public static function slugSource(): string
    {
        return self::string('posts.slugs.source', 'title');
    }

    public static function slugSeparator(): string
    {
        return self::string('posts.slugs.separator', '-');
    }

    /** The slug fallback locale; the app's fallback locale (else `en`) when unset. */
    public static function fallbackLocale(): string
    {
        if (config('posts.locales.fallback') !== null) {
            return self::string('posts.locales.fallback', 'en');
        }

        $app = config('app.fallback_locale');

        return is_string($app) && $app !== '' ? $app : 'en';
    }

    /** `posts.locales.fallback`, or null when unset (the caller then consults the app). */
    public static function configuredFallbackLocale(): ?string
    {
        return self::optionalString('posts.locales.fallback');
    }

    /** The default `twitter:card`: one of X's card types. */
    public static function defaultCard(): string
    {
        return Config::oneOf(
            'posts.seo.default-card',
            ['summary', 'summary_large_image', 'app', 'player'],
            'summary_large_image',
        );
    }

    public static function defaultRobots(): string
    {
        return self::string('posts.seo.default-robots', 'index,follow');
    }

    public static function siteName(): ?string
    {
        return self::optionalString('posts.seo.site-name');
    }

    public static function twitterSite(): ?string
    {
        return self::optionalString('posts.seo.twitter-site');
    }

    /** The schema.org `@type`: `BlogPosting` or `Article`. */
    public static function jsonLdType(): string
    {
        return Config::oneOf('posts.json-ld.type', ['BlogPosting', 'Article'], 'BlogPosting');
    }

    public static function jsonLdAuthorAttribute(): string
    {
        return self::string('posts.json-ld.author-attribute', 'name');
    }

    public static function publisherName(): ?string
    {
        return self::optionalString('posts.json-ld.publisher.name');
    }

    public static function publisherLogo(): ?string
    {
        return self::optionalString('posts.json-ld.publisher.logo');
    }

    public static function featuredBucket(): string
    {
        return self::string('posts.media.featured_bucket', 'featured');
    }

    public static function galleryBucket(): string
    {
        return self::string('posts.media.gallery_bucket', 'gallery');
    }

    public static function contentBucket(): string
    {
        return self::string('posts.media.content_bucket', 'content');
    }

    /** The post media disk, or null for media-library's default disk. */
    public static function mediaDisk(): ?string
    {
        return self::optionalString('posts.media.disk');
    }

    public static function featuredFallbackUrl(): ?string
    {
        return self::optionalString('posts.media.featured_fallback_url');
    }

    /**
     * The responsive width ladder (an empty list declares no variants), or null for
     * media-library's default ladder.
     *
     * @return list<int>|null
     */
    public static function responsiveWidths(): ?array
    {
        $key = 'posts.media.responsive_widths';
        $widths = config($key);

        if ($widths === null) {
            return null;
        }

        if (! is_array($widths) || ! array_is_list($widths)) {
            throw new InvalidConfigurationException(
                "Configuration value [{$key}] must be a list of positive integers, [".get_debug_type($widths).'] given.',
            );
        }

        $clean = [];

        foreach ($widths as $width) {
            // Validated under the setting's own key, so the message names it.
            $clean[] = Config::for([$key => $width])->integer($key, 1, 1);
        }

        return array_values(array_unique($clean));
    }

    /** The og:image fallback variant; `''` (the original) when unset. */
    public static function ogVariant(): string
    {
        return self::variant('posts.media.og_variant');
    }

    /** The variant an inline token renders by default; `''` (the original) when unset. */
    public static function inlineDefaultVariant(): string
    {
        return self::variant('posts.media.inline.default_variant');
    }

    /** `strip` or `keep` an inline token whose media is missing. */
    public static function inlineOnMissing(): string
    {
        return Config::oneOf('posts.media.inline.on_missing', ['strip', 'keep'], 'strip');
    }

    /** `archive` or `draft`, or null when the resolved-report path is disabled. */
    public static function onResolved(): ?string
    {
        return config('posts.moderation.on_resolved') === null
            ? null
            : Config::oneOf('posts.moderation.on_resolved', ['archive', 'draft'], 'archive');
    }

    private static function string(string $key, string $default): string
    {
        return self::optionalString($key) ?? $default;
    }

    private static function optionalString(string $key): ?string
    {
        $value = config($key);

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw InvalidConfigurationException::notAString($key, $value);
        }

        return $value;
    }

    /** A variant name: any string, `''` meaning the original; absent reads as `''`. */
    private static function variant(string $key): string
    {
        $value = config($key) ?? '';

        if (! is_string($value)) {
            throw new InvalidConfigurationException(
                "Configuration value [{$key}] must be a variant name (a string, '' for the original), [".get_debug_type($value).'] given.',
            );
        }

        return $value;
    }
}
