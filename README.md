# Posts for Laravel

A modern, multilingual, SEO-ready blog/posts engine for Laravel. Posts for Laravel gives you
translatable content, a real publishing lifecycle (draft / scheduled / published / archived),
a flexible taxonomy (nested categories + tags), per-post SEO meta and schema.org structured
data, and a polymorphic author that works with both bigint- and UUID-keyed models — all
config-first.

Built entirely on native Laravel: translatable attributes are stored as JSON columns and
resolved through a small package concern, with no third-party runtime dependencies.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/posts-for-laravel
```

Publish and run the migrations (set your author key type first — see Configuration):

```bash
php artisan vendor:publish --tag="posts-migrations"
php artisan migrate
```

Optionally publish the config, views, and translations:

```bash
php artisan vendor:publish --tag="posts-config"
php artisan vendor:publish --tag="posts-views"
php artisan vendor:publish --tag="posts-translations"
```

> **Author key type is fixed at first migrate.** The `author_id` column type is generated from
> `posts.author.key-type` when the migration runs. Choose `bigint` (default) or `uuid` to match
> your author model's primary key **before** running `migrate`. Changing it later requires a new
> additive migration.

> **PostgreSQL:** translatable columns ship as `json`. If you want indexed JSON queries, change
> them to `jsonb` in the published migration before migrating.

## Configuration

The published `config/posts.php`:

| Key | Type | Default | Env | Purpose |
|---|---|---|---|---|
| `model` | class-string | `Post::class` | — | The Post model (point at your subclass to extend). |
| `tables.posts` | string | `posts` | — | Posts table name. |
| `tables.categories` | string | `post_categories` | — | Categories table name. |
| `tables.category_post` | string | `category_post` | — | Category/post pivot table. |
| `tables.tags` | string | `post_tags` | — | Tags table name. |
| `tables.tag_post` | string | `post_tag` | — | Tag/post pivot table. |
| `author.key-type` | `bigint`\|`uuid` | `bigint` | `POSTS_AUTHOR_KEY_TYPE` | Author morph key column type. |
| `author.morph-name` | string | `author` | — | Morph relation name (`author_type`/`author_id`). |
| `author.nullable` | bool | `true` | — | Whether a post may have no author. |
| `locales.default` | string | `app.locale` | `POSTS_DEFAULT_LOCALE` | Default content locale. |
| `locales.fallback` | string | `app.fallback_locale` | `POSTS_FALLBACK_LOCALE` | Fallback locale for translations/route binding. |
| `locales.available` | list<string> | `['en']` | — | Locales iterated when generating slugs. |
| `slugs.source` | string | `title` | — | Attribute slugs are generated from. |
| `slugs.separator` | string | `-` | — | Slug word separator. |
| `slugs.unique` | bool | `true` | — | Suffix colliding slugs (`-2`, `-3`, …). |
| `slugs.route-binding` | bool | `true` | — | Bind `{post:slug}` by the translated slug. |
| `seo.site-name` | ?string | `null` | `POSTS_SITE_NAME` | Site name for SEO output. |
| `seo.twitter-site` | ?string | `null` | `POSTS_TWITTER_SITE` | Default `twitter:site` handle. |
| `seo.default-card` | string | `summary_large_image` | — | Default Twitter card type. |
| `seo.default-robots` | string | `index,follow` | — | Default robots directive. |
| `json-ld.type` | string | `BlogPosting` | — | schema.org `@type` (`BlogPosting` or `Article`). |
| `json-ld.author-attribute` | string | `name` | — | Author model attribute used for the author name. |
| `json-ld.publisher.name` | ?string | `null` | `POSTS_PUBLISHER_NAME` | Publisher organisation name. |
| `json-ld.publisher.logo` | ?string | `null` | `POSTS_PUBLISHER_LOGO` | Publisher logo URL. |

## Usage

### Authoring multilingual posts

Add the `HasPosts` trait to any author model (bigint or UUID keyed):

```php
use RoundlyConsulting\Posts\Concerns\HasPosts;

final class User extends Authenticatable
{
    use HasPosts; // $user->posts (morphMany)
}
```

Create a post with translated content. Each translatable attribute (`title`, `slug`, `perex`,
`content`, `meta_title`, `meta_description`) is stored as a JSON map of locale => value.
Slugs are generated per locale from the title, de-duplicated automatically, and a manually set
slug is preserved:

```php
use RoundlyConsulting\Posts\Models\Post;

$post = new Post;
$post->setTranslation('title', 'en', 'Hello world');
$post->setTranslation('title', 'sk', 'Ahoj svet');
$post->setTranslation('content', 'en', '<p>…</p>');
$post->save();

$post->author()->associate($user);   // polymorphic author
$post->save();

// Read a translation
$post->getTranslation('title', 'sk');     // 'Ahoj svet'
$post->getTranslations('title');          // ['en' => 'Hello world', 'sk' => 'Ahoj svet']
$post->translate('title');                // current-locale value, with fallback
$post->title;                             // same — current-locale value, with fallback
```

Translations resolve to the requested locale, then the fallback locale
(`translatable.fallback_locale`, else `posts.locales.fallback`, else `app.fallback_locale`),
then the first available translation.

### Publishing lifecycle

Posts move through a `PostStatus` enum (`Draft`, `Scheduled`, `Published`, `Archived`) with a
`published_at` timestamp. Transition methods fire events:

```php
$post->publish();                     // PostPublished
$post->schedule(now()->addDay());     // PostScheduled
$post->archive();                     // PostArchived
$post->draft();                       // PostDrafted
```

Query scopes:

```php
Post::query()->published()->get();    // status = published AND published_at <= now
Post::query()->draft()->get();
Post::query()->scheduled()->get();    // scheduled, or published with a future date
Post::query()->archived()->get();
```

Run the bundled command (or schedule it) to publish posts whose scheduled time has arrived:

```bash
php artisan posts:publish-scheduled
```

```php
// app/Console/Kernel.php
$schedule->command('posts:publish-scheduled')->everyMinute();
```

### Categories and tags

```php
use RoundlyConsulting\Posts\Models\Category;

$guides = Category::create(['name' => ['en' => 'Guides']]);
$laravel = Category::create(['name' => ['en' => 'Laravel'], 'parent_id' => $guides->id]);

$post->categories()->sync([$guides->id, $laravel->id]);

// Tags: pass strings (find-or-created by translated name) or Tag models
$post->syncTags(['eloquent', 'php']);

// Filter
Post::query()->inCategory($laravel)->get();
Post::query()->inCategory('laravel')->get();   // by translated slug
Post::query()->withTag('php')->get();

// Category hierarchy helpers
$laravel->ancestors();    // [Guides]
$guides->descendants();   // [Laravel, …]
```

Category and tag `name` and `slug` are both translatable, and slugs are auto-generated from
the name.

### SEO meta and structured data

`meta_title` / `meta_description` are translatable; the remaining SEO fields live in a
non-translatable `seo` bag. Sensible fallbacks are applied (meta title → title, meta
description → perex):

```php
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;

$post->setSeo(new SeoData(
    metaTitle: 'Custom title',
    canonical: 'https://example.test/posts/hello-world',
    ogImage: 'https://example.test/cover.png',
    robots: 'index,follow',
));
$post->save();

// In a Blade layout:
{!! $post->renderMetaTags() !!}   // <title>, description, canonical, og:*, twitter:*, robots
{!! $post->renderJsonLd() !!}     // <script type="application/ld+json"> BlogPosting/Article

$post->seo();        // SeoData with merged config defaults + fallbacks
$post->toJsonLd();   // array<string, mixed>
```

### Route-model binding by translated slug

With `posts.slugs.route-binding` enabled, `{post:slug}` resolves by the current locale's slug,
falling back to the configured fallback locale:

```php
Route::get('/posts/{post:slug}', fn (Post $post) => view('posts.show', compact('post')));
```

### Actions (DTO entry points)

```php
use RoundlyConsulting\Posts\Actions\CreatePostAction;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostTranslationData;
use RoundlyConsulting\Posts\Enums\PostStatus;

app(CreatePostAction::class)->execute(new CreatePostData(
    translations: [
        new CreatePostTranslationData(locale: 'en', title: 'Hello', content: '<p>…</p>'),
    ],
    status: PostStatus::Published,
    authorType: $user->getMorphClass(),
    authorId: $user->getKey(),
));
```

`PublishPostAction` and `UpdatePostSeoAction` are also available.

### Events

`PostPublished`, `PostScheduled`, `PostArchived`, `PostDrafted` (each carrying the `postId`)
are dispatched on the matching transition — listen for them to extend behaviour.

> **Media:** post image/video attachments are intentionally **not** part of this package. A
> dedicated in-house media package for Laravel will cover that separately. Until then, set
> `og:image` (and any post imagery) explicitly via `SeoData` / your own storage.

## Migrating from the previous version (pre-release)

This unreleased version replaces the old `visible` boolean and single `content` column. For any
early adopter: `visible = true → status = published, published_at = now`;
`visible = false → status = draft`; move the old `content` into the `content` JSON translation
for your default locale. The `author-model` config is gone — authorship is now polymorphic.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
