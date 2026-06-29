<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Models\Post;

return [

    /*
    |--------------------------------------------------------------------------
    | Post Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to represent a post. Extend the bundled model to
    | add your own behaviour and point this key at your subclass.
    |
    */

    'model' => Post::class,

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | The database table names used by the package. Override any of them if the
    | defaults collide with tables that already exist in your application.
    |
    */

    'tables' => [
        'posts' => 'posts',
        'categories' => 'post_categories',
        'category_post' => 'category_post',
        'tags' => 'post_tags',
        'tag_post' => 'post_tag',
    ],

    /*
    |--------------------------------------------------------------------------
    | Author
    |--------------------------------------------------------------------------
    |
    | Posts are authored through a polymorphic relationship, so any model in
    | your application can be an author. "key-type" controls the column type of
    | the author_id morph key and must match your author model's primary key
    | (allowed: "bigint" or "uuid"). It is fixed when the migration first runs.
    |
    */

    'author' => [
        'key-type' => env('POSTS_AUTHOR_KEY_TYPE', 'bigint'),
        'morph-name' => 'author',
        'nullable' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Translatable attributes (title, slug, perex, content, SEO meta and the
    | taxonomy names) are stored per locale. "available" lists the locales the
    | package iterates when generating slugs.
    |
    */

    'locales' => [
        'default' => env('POSTS_DEFAULT_LOCALE', config('app.locale', 'en')),
        'fallback' => env('POSTS_FALLBACK_LOCALE', config('app.fallback_locale', 'en')),
        'available' => ['en'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Slugs
    |--------------------------------------------------------------------------
    |
    | Slugs are generated per locale from the "source" attribute. When "unique"
    | is enabled, colliding slugs are suffixed (-2, -3, ...). When
    | "route-binding" is enabled, {post:slug} resolves by the translated slug.
    |
    */

    'slugs' => [
        'source' => 'title',
        'separator' => '-',
        'unique' => true,
        'route-binding' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    |
    | Defaults applied when a post does not provide its own SEO values. The
    | rendered meta tags fall back to the post's title and perex when these and
    | the post-level overrides are absent.
    |
    */

    'seo' => [
        'site-name' => env('POSTS_SITE_NAME'),
        'twitter-site' => env('POSTS_TWITTER_SITE'),
        'default-card' => 'summary_large_image',
        'default-robots' => 'index,follow',
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON-LD Structured Data
    |--------------------------------------------------------------------------
    |
    | Controls the schema.org structured data emitted for a post. "type" is the
    | schema.org @type ("BlogPosting" or "Article"); "author-attribute" is the
    | attribute read from the author model for the author name.
    |
    */

    'json-ld' => [
        'type' => 'BlogPosting',
        'author-attribute' => 'name',
        'publisher' => [
            'name' => env('POSTS_PUBLISHER_NAME'),
            'logo' => env('POSTS_PUBLISHER_LOGO'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | Integration with roundly-consulting/media-library-for-laravel. A post owns
    | three media buckets: a single-file "featured" image, a multi-file "gallery",
    | and a "content" bucket holding the media referenced inline by [media:UUID]
    | tokens in the post body. Inline tokens resolve ONLY against the post's own
    | content bucket, never arbitrary global media.
    |
    */

    'media' => [

        // Bucket names the post registers on the media-library model.
        'featured_bucket' => 'featured',
        'gallery_bucket' => 'gallery',
        'content_bucket' => 'content',

        // Disk for the post's media. null => the media-library default disk.
        'disk' => env('POSTS_MEDIA_DISK'),

        // Fallback URL returned by featuredImageUrl() when no featured image is set.
        // null => an empty string is returned instead.
        'featured_fallback_url' => env('POSTS_MEDIA_FEATURED_FALLBACK'),

        // Responsive width ladder for featured/gallery/content images.
        // null => the media-library default ladder (config('media.responsive.widths')).
        'responsive_widths' => null,

        // Fall back the SEO og:image / JSON-LD image to the featured image URL
        // when no explicit og:image is set on the post.
        'seo_og_image' => true,

        // Variant name used for the og:image fallback ('' => the original).
        'og_variant' => '',

        // Dispatch a queued media GenerateVariantsJob for the post's media on publish.
        'warm_on_publish' => true,

        // Inline [media:UUID] / [media:UUID|variant] rendering in the post body.
        'inline' => [
            'enabled' => true,
            'default_variant' => '',
            'on_missing' => 'strip', // 'strip' | 'keep'
        ],
    ],

];
