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
    | Key Type (outbound — the models a post points at)
    |--------------------------------------------------------------------------
    |
    | The primary-key strategy of the models a post is authored by. It sets the
    | column type of the author morph key and must match your author model's
    | primary key: "bigint" (the Laravel default), "uuid" or "ulid". Anything
    | else throws an InvalidConfigurationException. It is fixed when the
    | migration first runs, so choose it before publishing the migrations.
    |
    | This is your AUTHOR model's key type, not the post's own — see
    | "primary_key_type" below. The two are independent: a host with bigint users
    | and uuid posts is a perfectly ordinary application.
    |
    */

    'key_type' => env('POSTS_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Primary Key Type (inbound — the posts tables' own ids)
    |--------------------------------------------------------------------------
    |
    | The primary-key strategy of the package's own tables — posts, categories and
    | tags, plus the pivots and the category parent link that reference them:
    | "bigint" (the Laravel default), "uuid" or "ulid". Anything else throws an
    | InvalidConfigurationException.
    |
    | This is the key OTHER packages' polymorphic columns point at. A morph column
    | (`likeable_id`, `reportable_id`, ...) defaults to an unsigned bigint, so on a
    | strict engine such as PostgreSQL a non-bigint post id cannot be liked or
    | reported at all. Change this only if every morph target in your application
    | shares the same key type — see "Key types" in the README.
    |
    | It is fixed when the migration first runs, so choose it before publishing the
    | migrations.
    |
    */

    'primary_key_type' => env('POSTS_PRIMARY_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | The database table names used by the package. Override any of them if the
    | defaults collide with tables that already exist in your application.
    |
    | Every string setting in this file is read strictly: a default applies only
    | when the key is unset (null). A blank or non-string value throws an
    | InvalidConfigurationException instead of being cast or replaced.
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
    | your application can be an author. "morph-name" names the morph column
    | pair on the posts table (author_type / author_id) and "nullable" decides
    | whether a post may exist without an author. The id column's type comes
    | from "key_type" above.
    |
    */

    'author' => [
        'morph-name' => 'author',
        'nullable' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Translatable attributes (title, slug, perex, content, SEO meta and the
    | taxonomy names) are stored per locale, and read for the application's
    | current locale. "fallback" is the locale a translation falls back to when
    | the current one is missing.
    |
    */

    'locales' => [
        'fallback' => env('POSTS_FALLBACK_LOCALE', config('app.fallback_locale', 'en')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slugs
    |--------------------------------------------------------------------------
    |
    | Posts, categories and tags carry a per-locale slug map, handled by
    | roundly-consulting/sluggable-for-laravel. Post slugs are generated from
    | "source" (categories and tags from their name) for every locale that has
    | one, and missing locales are filled on each save that changes the model.
    |
    | "unique"       suffix colliding slugs per locale (-2, -3, ...), trashed
    |                rows included, backed by per-locale unique indexes that
    |                the create migrations build when this is on at migrate time.
    | "route-binding" make the post slug the route key: {post} resolves by the
    |                current locale's slug, then the fallback locale, then any
    |                locale, and route('...', $post) emits that slug.
    | "history"      remember retired post slugs and answer old URLs with a 301
    |                (publish sluggable's migrations first).
    | "lock-when-published" freeze a post's slugs once it is published: nothing
    |                regenerates them and a manual change throws.
    |
    */

    'slugs' => [
        'source' => 'title',
        'separator' => '-',
        'unique' => true,
        'route-binding' => true,
        'history' => env('POSTS_SLUG_HISTORY', false),
        'lock-when-published' => false,
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
        'default-card' => 'summary_large_image', // 'summary' | 'summary_large_image' | 'app' | 'player'
        'default-robots' => 'index,follow',
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON-LD Structured Data
    |--------------------------------------------------------------------------
    |
    | Controls the schema.org structured data emitted for a post. "type" is the
    | schema.org @type ("BlogPosting" or "Article"; anything else throws); "author-attribute" is the
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

        // Responsive width ladder for featured/gallery/content images: positive integers
        // ([] declares no variants). null => the media-library default ladder
        // (config('media.responsive.widths')).
        'responsive_widths' => null,

        // Fall back the SEO og:image / JSON-LD image to the featured image URL
        // when no explicit og:image is set on the post.
        'seo_og_image' => true,

        // Variant name used for the og:image fallback ('' => the original). Name a
        // variant media-library generates for the featured bucket (e.g. 'responsive-640');
        // until it exists — or if the name is unknown — the original's URL is used.
        'og_variant' => '',

        // Dispatch a queued media GenerateVariantsJob for the post's media on publish.
        'warm_on_publish' => true,

        // Inline [media:UUID] / [media:UUID|variant] rendering in the post body.
        // "default_variant" applies to tokens without a |variant. A variant that is
        // unknown or not generated yet renders the original image instead.
        'inline' => [
            'enabled' => true,
            'default_variant' => '',
            'on_missing' => 'strip', // 'strip' | 'keep' (anything else throws)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation
    |--------------------------------------------------------------------------
    |
    | Integration with roundly-consulting/reports-for-laravel. When a report
    | against a post is upheld, or the post crosses the global reports threshold
    | (config('reports.threshold')), the post can be auto-unpublished through its
    | own lifecycle (re-emitting PostArchived / PostDrafted). A scheduled post is
    | taken off the schedule the same way, so it never goes live; drafts and
    | archived posts are left alone. Because reports
    | routes resolution through approvals, this yields multi-moderator moderation
    | with no extra code. Set both keys to disable auto-moderation entirely.
    |
    */

    'moderation' => [

        // Auto-unpublish a post when a report against it is upheld (ReportResolved).
        // 'archive' | 'draft' | null (disable the resolved path). Anything else throws.
        'on_resolved' => 'archive',

        // Auto-unpublish (archive) a post when its open-report count crosses the
        // global config('reports.threshold') — reacts to ReportThresholdReached.
        'auto_unpublish' => true,
    ],

];
