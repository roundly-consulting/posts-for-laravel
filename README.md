# Posts for Laravel

Store text, image, and video posts with media attachments and attach them to any author
model (typically your application's `User`). Media handling is powered by
[acme/laravel-medialibrary](https://github.com/acme/laravel-medialibrary), including an
automatic `preview` thumbnail conversion and optional responsive images.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/posts-for-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="posts-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="posts-config"
```

Because the package stores media, make sure the
[Media Library prerequisites](https://example.com/docs/laravel-medialibrary/installation-setup)
are met (its `media` table migration is published with `php artisan vendor:publish --provider="Acme\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"`).

## Configuration

The published `config/posts.php` looks like this:

```php
<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Models\Post;

return [
    'model' => Post::class,
    'author-model' => env('POSTS_AUTHOR_MODEL'),
    'disk' => env('POSTS_DISK', env('MEDIA_DISK', 'public')),
    'responsive-images' => env('POSTS_GENERATE_RESPONSIVE_IMAGES', true),
    'queue-file-conversions' => env('POSTS_QUEUE_FILE_CONVERSIONS', env('QUEUE_CONVERSIONS_BY_DEFAULT', true)),
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `model` | `class-string<Post>` | `RoundlyConsulting\Posts\Models\Post` | The Post Eloquent model. Point this at a subclass to extend behaviour. |
| `author-model` | `class-string` \| `null` | `POSTS_AUTHOR_MODEL` env, else `null` | **Required.** The model that authors posts — usually your `User` model. |
| `disk` | `string` | `POSTS_DISK`, else `MEDIA_DISK`, else `public` | Filesystem disk used to store media attachments. |
| `responsive-images` | `bool` | `POSTS_GENERATE_RESPONSIVE_IMAGES`, else `true` | Whether responsive image variants are generated. |
| `queue-file-conversions` | `bool` | `POSTS_QUEUE_FILE_CONVERSIONS`, else `QUEUE_CONVERSIONS_BY_DEFAULT`, else `true` | Generate the `preview` conversion on the queue (`true`) or synchronously (`false`). |

### Environment variables

| Variable | Backs |
|---|---|
| `POSTS_AUTHOR_MODEL` | `posts.author-model` |
| `POSTS_DISK` / `MEDIA_DISK` | `posts.disk` |
| `POSTS_GENERATE_RESPONSIVE_IMAGES` | `posts.responsive-images` |
| `POSTS_QUEUE_FILE_CONVERSIONS` / `QUEUE_CONVERSIONS_BY_DEFAULT` | `posts.queue-file-conversions` |

Set the author model in your `.env` (or override the config key directly):

```dotenv
POSTS_AUTHOR_MODEL=App\Models\User
```

## Usage

### Make a model author posts

Add the `HasPosts` trait to the model named in `posts.author-model`:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Posts\Concerns\HasPosts;

class User extends Model
{
    use HasPosts;
}
```

The trait exposes a `posts()` relationship:

```php
$user->posts;          // Collection<int, Post>
$user->posts()->count();
```

### Create a post and attach media

```php
$post = $user->posts()->create([
    'content' => 'This lake looks awesome.',
]);

// Attach an upload from the current request...
$post->addMediaFromRequest('attachment')->toMediaCollection();

// ...or any UploadedFile / path.
$post->addMedia($uploadedFile)->toMediaCollection();
```

### Read media URLs

Every attachment lands in the `default` collection and gets a `preview` conversion you can
use as a thumbnail:

```php
echo $post->getFirstMediaUrl();                         // original attachment
echo $post->getFirstMediaUrl(conversionName: 'preview'); // 250x250 cropped thumbnail
```

### The Post model

`RoundlyConsulting\Posts\Models\Post` is a UUID-keyed Eloquent model with:

- `content` — optional long text body.
- `visible` — boolean flag (defaults to `true`).
- `author()` — `belongsTo` the configured `author-model` via `author_id`.
- A media library `default` collection with a `preview` (250x250 crop, sharpened) conversion.

It ships a factory for tests and seeders:

```php
use RoundlyConsulting\Posts\Models\Post;

Post::factory()->create();
Post::factory()->hidden()->create(); // visible = false
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for what has changed recently.

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
