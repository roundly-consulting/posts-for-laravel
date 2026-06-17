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

];
