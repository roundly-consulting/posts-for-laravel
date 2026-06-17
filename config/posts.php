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
    | Author Model
    |--------------------------------------------------------------------------
    |
    | The fully-qualified class name of the model that authors posts. For most
    | applications this is your User model. This is required before posts can
    | be associated with an author.
    |
    */

    'author-model' => env('POSTS_AUTHOR_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Media Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk used to store media attachments for posts.
    |
    */

    'disk' => env('POSTS_DISK', env('MEDIA_DISK', 'public')),

    /*
    |--------------------------------------------------------------------------
    | Responsive Images
    |--------------------------------------------------------------------------
    |
    | Whether responsive image variants are generated for image attachments.
    |
    */

    'responsive-images' => env('POSTS_GENERATE_RESPONSIVE_IMAGES', true),

    /*
    |--------------------------------------------------------------------------
    | Queue File Conversions
    |--------------------------------------------------------------------------
    |
    | When true, the "preview" conversion is generated on the queue. When false
    | it is generated synchronously during the request lifecycle.
    |
    */

    'queue-file-conversions' => env('POSTS_QUEUE_FILE_CONVERSIONS', env('QUEUE_CONVERSIONS_BY_DEFAULT', true)),

];
