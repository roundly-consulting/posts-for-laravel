<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/posts-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=posts-for-laravel">
    <img src="art/hero.png" alt="Posts for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/posts-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/posts-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/posts-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/posts-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/posts-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/posts-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=posts-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

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

## Integrates with

Posts builds directly on our own packages (installed automatically as dependencies — their
service providers auto-discover, so there is nothing extra to register):

- [`enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel) — `PostStatus`
  adopts its `Helpers` trait (`labels()`/`options()`/`validationRule()`/…). See **Status labels &
  select options**.
- [`package-toolkit-for-laravel`](https://github.com/roundly-consulting/package-toolkit-for-laravel) —
  the service provider, the `posts.key_type` schema strategy (bigint/uuid/ulid) and the `posts.model`
  resolver are the toolkit's.
- [`media-library-for-laravel`](https://github.com/roundly-consulting/media-library-for-laravel) —
  featured image, gallery and inline `[media:UUID]` content buckets on the bundled `Post`. See
  **Media**.
- [`likes-for-laravel`](https://github.com/roundly-consulting/likes-for-laravel) — the `Post` is a
  `Likeable`: like/unlike/toggle, live + eager counts, popular/trending feed scopes, single-query
  viewer-state hydration, and a compact like payload. See **Likes & reactions**.
- [`reports-for-laravel`](https://github.com/roundly-consulting/reports-for-laravel) — the `Post`
  is a `Reportable`: report-a-post with dedup, typed reasons and guest reports, moderation-queue
  scopes, and multi-moderator sign-off (routed through
  [`approvals-for-laravel`](https://github.com/roundly-consulting/approvals-for-laravel), which
  arrives transitively). Upheld reports / threshold crossings can auto-unpublish a post. See
  **Reports & moderation**.
- [`sluggable-for-laravel`](https://github.com/roundly-consulting/sluggable-for-laravel) — `Post`,
  `Category` and `Tag` are `Sluggable`: per-locale slugs with bounded collision probing that global
  scopes and soft deletes can't hide, per-locale unique indexes with race retry, locale-aware route
  binding, optional slug history with 301s, a lock for published URLs, validation rules and
  backfill commands. See **Slugs**.

All of them sit in a strictly lower dependency tier than posts (enums/sluggable T0 ·
media/likes/approvals T1 · reports T2 · posts T3), so the dependency graph stays acyclic.

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/posts-for-laravel
```

Publish and run the migrations. **Migrations are publish-only** — nothing is auto-loaded, so a bare
`php artisan migrate` will not create the package's tables until you publish them. Set your key type
first (see Configuration), because the author column type is baked in at publish/migrate time:

```bash
php artisan vendor:publish --tag="posts-migrations"

# The packages posts builds on are publish-only too:
php artisan vendor:publish --tag="media-migrations"
php artisan vendor:publish --tag="likes-migrations"
php artisan vendor:publish --tag="reports-migrations"
php artisan vendor:publish --tag="approvals-migrations"

# Only if you turn on slug history (posts.slugs.history):
php artisan vendor:publish --tag="sluggable-migrations"

php artisan migrate
```

> **Slug indexes are built for the locales known at migrate time.** The posts, categories and
> tags migrations add one unique index per supported locale on `slug`. The locale list comes
> from sluggable (`sluggable.locales.supported`, or translatable-for-laravel's locales when it is
> installed, else `app.locale` + `app.fallback_locale`) — set it **before** `migrate`. Add
> locales later with `php artisan sluggable:indexes "RoundlyConsulting\Posts\Models\Post"` (and
> the same for `Category` and `Tag`). On SQL Server, which sluggable cannot index, the index step
> is skipped and slug uniqueness stays application-level.

Optionally publish the config and views:

```bash
php artisan vendor:publish --tag="posts-config"
php artisan vendor:publish --tag="posts-views"
```

> **Key types are fixed at first migrate.** Both `posts.key_type` (your author model's key) and
> `posts.primary_key_type` (the posts tables' own ids) are read when the migration runs. Choose
> them **before** running `migrate`; an unrecognized value throws `InvalidConfigurationException`
> rather than migrating as `bigint`, and changing either later is a data migration. See [Key types](#key-types).

> **PostgreSQL:** translatable columns ship as `jsonb`; the slug indexes are expression indexes
> on `slug->>'<locale>'`, so slug lookups and route binding use them.

## Configuration

The published `config/posts.php`:

| Key | Type | Default | Env | Purpose |
|---|---|---|---|---|
| `model` | class-string | `Post::class` | — | The Post model (point at your subclass to extend). |
| `key_type` | `bigint`\|`uuid`\|`ulid` | `bigint` | `POSTS_KEY_TYPE` | **Outbound**: your *author* model's key type, which sets the `author_id` morph column. Anything else throws `InvalidConfigurationException`. |
| `primary_key_type` | `bigint`\|`uuid`\|`ulid` | `bigint` | `POSTS_PRIMARY_KEY_TYPE` | **Inbound**: the key type of posts' *own* tables (posts, tags, categories and their pivots). Anything else throws `InvalidConfigurationException`. See [Key types](#key-types). |
| `tables.posts` | string | `posts` | — | Posts table name. |
| `tables.categories` | string | `post_categories` | — | Categories table name. |
| `tables.category_post` | string | `category_post` | — | Category/post pivot table. |
| `tables.tags` | string | `post_tags` | — | Tags table name. |
| `tables.tag_post` | string | `post_tag` | — | Tag/post pivot table. |
| `author.morph-name` | string | `author` | — | Morph relation name (`author_type`/`author_id`). |
| `author.nullable` | bool | `true` | — | Whether a post may have no author. |
| `locales.fallback` | string | `app.fallback_locale` | `POSTS_FALLBACK_LOCALE` | Fallback locale for translations/route binding. |
| `slugs.source` | string | `title` | — | Attribute slugs are generated from. |
| `slugs.separator` | string | `-` | — | Slug word separator. |
| `slugs.unique` | bool | `true` | — | Suffix colliding slugs per locale (`-2`, `-3`, …), trashed rows included; at migrate time, also builds the per-locale unique indexes. |
| `slugs.route-binding` | bool | `true` | — | Make the slug the post's route key: `{post}` binds by the translated slug and `route(…, $post)` emits it. |
| `slugs.history` | bool | `false` | `POSTS_SLUG_HISTORY` | Remember retired post slugs and 301 old URLs to the current one (needs sluggable's migration). |
| `slugs.lock-when-published` | bool | `false` | — | Freeze a published post's slugs: no regeneration, and a manual change throws `SlugLockedException`. |
| `seo.site-name` | ?string | `null` | `POSTS_SITE_NAME` | Default `og:site_name` (a post can override it). |
| `seo.twitter-site` | ?string | `null` | `POSTS_TWITTER_SITE` | Default `twitter:site` handle. |
| `seo.default-card` | string | `summary_large_image` | — | Default Twitter card type: `summary`, `summary_large_image`, `app` or `player`. |
| `seo.default-robots` | string | `index,follow` | — | Default robots directive. |
| `json-ld.type` | `BlogPosting`\|`Article` | `BlogPosting` | — | schema.org `@type`. |
| `json-ld.author-attribute` | string | `name` | — | Author model attribute used for the author name. |
| `json-ld.publisher.name` | ?string | `null` | `POSTS_PUBLISHER_NAME` | Publisher organisation name. |
| `json-ld.publisher.logo` | ?string | `null` | `POSTS_PUBLISHER_LOGO` | Publisher logo URL. |
| `media.featured_bucket` | string | `featured` | — | Single-file featured-image bucket name. |
| `media.gallery_bucket` | string | `gallery` | — | Multi-file gallery bucket name. |
| `media.content_bucket` | string | `content` | — | Bucket owning media referenced inline by `[media:UUID]`. |
| `media.disk` | ?string | `null` | `POSTS_MEDIA_DISK` | Disk for post media (`null` = media-library default). |
| `media.featured_fallback_url` | ?string | `null` | `POSTS_MEDIA_FEATURED_FALLBACK` | URL `featuredImageUrl()` returns when no featured image is set. |
| `media.responsive_widths` | ?list<int> | `null` | — | Responsive width ladder of positive integers (`null` = media-library default, `[]` = no variants). |
| `media.seo_og_image` | bool | `true` | — | Fall back `og:image`/JSON-LD `image` to the featured image. |
| `media.og_variant` | string | `''` | — | Variant used for the og:image fallback (`''` = original). Name a variant media-library generates for the featured bucket (e.g. `responsive-640`); until it exists, or for an unknown name, the original's URL is used. |
| `media.warm_on_publish` | bool | `true` | — | Queue variant generation for the post's media on publish. |
| `media.inline.enabled` | bool | `true` | — | Expand `[media:UUID]` tokens in rendered content. |
| `media.inline.default_variant` | string | `''` | — | Variant applied to inline tokens with no `\|variant`. Like a token's own `\|variant`, one that is unknown or not generated yet renders the original image. |
| `media.inline.on_missing` | `strip`\|`keep` | `strip` | — | Drop or keep tokens whose media is missing/unauthorized. |
| `moderation.on_resolved` | `archive`\|`draft`\|`null` | `archive` | — | Auto-unpublish action when a report against a published or scheduled post is upheld (`null` = disable). |
| `moderation.auto_unpublish` | bool | `true` | — | Auto-archive a published or scheduled post when it crosses the global `reports.threshold`. |

Every `bool` switch is read strictly: `true`/`1`/`on`/`yes` turn it on, `false`/`0`/`off`/`no`
turn it off, and anything else (say `POSTS_SLUG_HISTORY=disabled`) throws
`InvalidConfigurationException` instead of quietly reading as the default.

Every other setting is just as strict. A default applies only when the key is absent (unset or
`null`). A string setting — a table or bucket name, the morph name, the slug source or
separator, a locale, an SEO / JSON-LD value, the media disk or fallback URL — must be a
non-empty string when set: a blank (`POSTS_SITE_NAME=` included) or non-string value throws
rather than being cast to `''` or replaced by the default. A `seo.default-card`, `json-ld.type`,
`media.inline.on_missing` or `moderation.on_resolved` value outside its list throws (an
`on_resolved` typo no longer skips the auto-unpublish), and so does a responsive width that is
not a positive integer. The variant names (`media.og_variant`, `media.inline.default_variant`)
take any string, `''` meaning the original. `php artisan about` renders a broken non-boolean
setting as `INVALID`.

### Key types

Posts has **two independent key-type settings**, because there are two different keys and
they belong to different owners. Mixing them up is easy and expensive, so they are named
apart:

| Config | Axis | What it types | Who owns the model |
|---|---|---|---|
| `key_type` | **outbound** | `author_id` — the morph column pointing *at* your author model | **you** (your `User`, `Team`, …) |
| `primary_key_type` | **inbound** | `posts.id`, `post_tags.id`, `post_categories.id` + their pivots — the ids *other* things point at | **this package** |

They are genuinely independent: a host with `bigint` users and `uuid` posts is an ordinary
application, and both default to `bigint`.

```dotenv
POSTS_KEY_TYPE=uuid            # your User model is uuid-keyed
POSTS_PRIMARY_KEY_TYPE=bigint  # posts' own ids stay bigint
```

**Why `primary_key_type` defaults to `bigint`.** A post is a thing other packages point at
polymorphically — it is likeable, reportable, commentable. A Laravel morph column
(`$table->morphs('likeable')`) is an unsigned bigint. On a strict engine such as PostgreSQL,
a `uuid` post id simply will not go into one:

```
SQLSTATE[22P02]: invalid input syntax for type bigint: "019f6f33-22b8-737f-a581-849e7cdc517a"
```

SQLite will **not** warn you about this — its type affinity stores the string in an integer
column silently, so a green SQLite suite proves nothing here. (Note this needs no foreign
key to go wrong: a polymorphic column cannot carry one.)

> **Constraint:** `primary_key_type` assumes every morph target in your application shares one
> key type. If you set `POSTS_PRIMARY_KEY_TYPE=uuid`, then every other model your likes /
> reports / comments point at needs to be uuid-keyed too, and the packages owning those
> columns need to agree. A mixed application — a `uuid` `Post` and a `bigint` `Comment` both
> likeable through the same column — is not supported by this package, by Laravel's own
> `morphs()`/`uuidMorphs()` split, or by anything else. Pick one key type per application.

## Usage

### The `Posts` facade

Everything a host does with posts goes through one facade, `RoundlyConsulting\Posts\Facades\Posts`
(also aliased as `Posts`):

```php
use RoundlyConsulting\Posts\Facades\Posts;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;

// Write a post with the builder, then save it as a draft, publish it or schedule it
$post = Posts::draft()
    ->title('en', 'Hello world')->title('sk', 'Ahoj svet')
    ->perex('en', 'A short intro')
    ->content('en', '<p>…</p>')
    ->by($user)                                   // any author model, bigint/uuid/ulid keyed
    ->tags(['laravel', 'php'])                    // Tag models, or names found/created in the current locale
    ->seo(new SeoData(canonical: 'https://example.test/hello-world'))
    ->publish();                                  // or ->save() (draft), ->publish($at), ->schedule($at)

// Or from a DTO
Posts::create($createPostData);

// Lifecycle
Posts::publish($post);                            // now, or Posts::publish($post, $at)
Posts::schedule($post, now()->addDay());
Posts::archive($post);
Posts::unpublish($post);                          // back to draft
Posts::publishDue();                              // publish every scheduled post whose time has come → int

// SEO and tags
Posts::seo($post, new SeoData(metaTitle: 'Custom title', robots: 'index,follow'));
Posts::syncTags($post, ['eloquent', $tag]);

// Reads — always the configured posts.model
Posts::findBySlug('hello-world');                 // current locale → fallback → any locale
Posts::findBySlug('ahoj-svet', 'sk');             // exactly one locale
Posts::published()->latest('published_at')->paginate();
Posts::query()->inCategory('laravel')->get();
```

| Method | Returns | What it does |
|---|---|---|
| `create(CreatePostData $data)` | `Post` | Create a post from a DTO (see below) |
| `draft()` | `PendingPost` | Builder: `title/slug/perex/content/metaTitle/metaDescription($locale, $value)`, `by($author)`, `tags([...])`, `seo(SeoData)`, then `save()`, `publish(?$at)`, `schedule($at)` or `data()` (the DTO) |
| `publish(Post $post, ?CarbonInterface $at = null)` | `Post` | Publish now or at `$at`; fires `PostPublished` |
| `schedule(Post $post, CarbonInterface $at)` | `Post` | Schedule; fires `PostScheduled` |
| `archive(Post $post)` | `Post` | Archive, keeping the publish date; fires `PostArchived` |
| `unpublish(Post $post)` | `Post` | Back to draft, clearing the publish date; fires `PostDrafted` |
| `seo(Post $post, SeoData $data)` | `Post` | Store the SEO fields and save |
| `syncTags(Post $post, iterable $tags)` | `Post` | Sync tags (models or current-locale names) |
| `publishDue()` | `int` | Publish every due scheduled post (what `posts:publish-scheduled` runs) |
| `findBySlug(string $slug, ?string $locale = null)` | `?Post` | Find by slug through the `posts.model` seam |
| `published()` | `Builder<Post>` | Published posts whose date has passed |
| `query()` | `Builder<Post>` | A query on the configured `posts.model` |

The model keeps its convenience methods — `$post->publish()`, `schedule()`, `archive()`,
`unpublish()` and `syncTags()` — and each of them goes through the same manager, so behaviour,
events and the fake are identical whichever form you use. A `posts.model` subclass can override
them to hook in.

#### Without the facade

The facade is sugar over `RoundlyConsulting\Posts\PostsManager`. Inject it for the same API:

```php
use RoundlyConsulting\Posts\PostsManager;

final class PublishController
{
    public function __construct(private PostsManager $posts) {}

    public function __invoke(Post $post): Post
    {
        return $this->posts->publish($post);
    }
}
```

Or call an action directly — each facade write is one action class resolved from the container
(`CreatePostAction`, `PublishPostAction`, `SchedulePostAction`, `ArchivePostAction`,
`UnpublishPostAction`, `UpdatePostSeoAction`, `SyncPostTagsAction`, `PublishDuePostsAction`):

```php
use RoundlyConsulting\Posts\Actions\CreatePostAction;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostTranslationData;
use RoundlyConsulting\Posts\Enums\PostStatus;

app(CreatePostAction::class)->execute(new CreatePostData(
    translations: [
        new CreatePostTranslationData(locale: 'en', title: 'Hello', content: '<p>…</p>'),
    ],
    status: PostStatus::Published,       // dated now when publishedAt is omitted; fires PostPublished
    authorType: $user->getMorphClass(),
    authorId: $user->getKey(),
));
```

A post created `Published` without a `publishedAt` is dated now; one created `Scheduled` must
carry a `publishedAt` (else `InvalidPostStatusTransitionException`). Creating a published or
scheduled post fires `PostPublished` / `PostScheduled`.

#### Testing with `Posts::fake()`

`Posts::fake()` swaps a recording fake in behind the facade **and** the container (it extends
`PostsManager`, so injected managers get it too). Every write — through the facade, an injected
manager, the builder, the model's lifecycle methods and `syncTags()`, the moderation listener or
`posts:publish-scheduled` — is recorded instead of run: nothing is written and no event fires.
`create()` returns an unsaved post; reads (`findBySlug()`, `published()`, `query()`) still hit
the database.

```php
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\Facades\Posts;

Posts::fake();

// … exercise your code …

Posts::assertCreated(fn (CreatePostData $data): bool => $data->translations[0]->title === 'Hello');
Posts::assertPublished($post);                 // optionally: Posts::assertPublished($post, $at)
Posts::assertScheduled($post, $at);
Posts::assertArchived($post);
Posts::assertUnpublished($post);
Posts::assertSeoUpdated($post, fn (SeoData $seo): bool => $seo->robots === 'noindex');
Posts::assertTagged($post, ['laravel', 'php']);
Posts::assertPublishedDue();
```

Each has a negative twin: `assertNothingCreated()`, `assertNothingPublished()`,
`assertNothingScheduled()`, `assertNothingArchived()`, `assertNothingUnpublished()`,
`assertNothingSeoUpdated()`, `assertNothingTagged()`, `assertNothingPublishedDue()`.

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
Slugs are generated per locale from the title and de-duplicated automatically; a manually set
slug is normalised (`Custom Slug` → `custom-slug`) and kept unique (see [Slugs](#slugs)):

```php
use RoundlyConsulting\Posts\Facades\Posts;

$post = Posts::draft()
    ->title('en', 'Hello world')
    ->title('sk', 'Ahoj svet')
    ->content('en', '<p>…</p>')
    ->by($user)                      // polymorphic author
    ->save();

// Plain Eloquent works too — edit translations on the model and save:
$post->setTranslation('perex', 'en', 'A short intro');
$post->save();

// Read a translation
$post->getTranslation('title', 'sk');     // 'Ahoj svet'
$post->getTranslations('title');          // ['en' => 'Hello world', 'sk' => 'Ahoj svet']
$post->translate('title');                // current-locale value, with fallback
$post->title;                             // same — current-locale value, with fallback
```

Translations resolve to the requested locale, then the fallback locale
(`translatable.fallback_locale`, else `posts.locales.fallback`, else `app.fallback_locale`),
then the lowest-sorting locale that holds a value (a stable choice, independent of the
order the database returns the JSON keys in).

### Publishing lifecycle

Posts move through a `PostStatus` enum (`Draft`, `Scheduled`, `Published`, `Archived`) with a
`published_at` timestamp. Transitions fire events; an archived post can only go back to draft
(anything else throws `InvalidPostStatusTransitionException`):

```php
Posts::publish($post);                     // PostPublished — or $post->publish()
Posts::schedule($post, now()->addDay());   // PostScheduled — or $post->schedule($at)
Posts::archive($post);                     // PostArchived  — or $post->archive()
Posts::unpublish($post);                   // PostDrafted   — or $post->unpublish()
```

Publishing, archiving or unpublishing a post that already has that status is a no-op: nothing
is written, the publish date is kept and no event fires (re-scheduling always moves the date).
Each move is decided against the **stored** status under a row lock, so a stale copy of a post
that a moderator archived meanwhile throws instead of going live.

Dates may carry any timezone — `now('Asia/Tokyo')->addHour()` — and are stored as that same
instant in the app timezone (`config('app.timezone')`), the zone `published()` and
`publishDue()` compare against.

Query scopes:

```php
Post::query()->published()->get();    // status = published AND published_at <= now
Post::query()->draft()->get();
Post::query()->scheduled()->get();    // scheduled, or published with a future date
Post::query()->archived()->get();
```

Run the bundled command (or schedule it) to publish posts whose scheduled time has arrived — it
calls `Posts::publishDue()`, which you can also call yourself:

```bash
php artisan posts:publish-scheduled
```

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('posts:publish-scheduled')->everyMinute()->withoutOverlapping()->onOneServer();
```

`withoutOverlapping()` / `onOneServer()` save work, but correctness does not depend on them:
each due post is claimed under a row lock (still scheduled, still due) before it is published, so
overlapping runs publish it — and fire `PostPublished` — once, and a post archived, drafted or
rescheduled after a run listed it is skipped. (`onOneServer()` needs a cache store shared by your
servers.)

`PostStatus` builds on
[`roundly-consulting/enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel),
so every case ships readable labels, select options, lookups, and a ready-made validation rule
(see [Status labels & select options](#status-labels--select-options)).

### Categories and tags

```php
use RoundlyConsulting\Posts\Models\Category;

$guides = Category::create(['name' => ['en' => 'Guides']]);
$laravel = Category::create(['name' => ['en' => 'Laravel'], 'parent_id' => $guides->id]);

$post->categories()->sync([$guides->id, $laravel->id]);

// Tags: pass strings (find-or-created by translated name) or Tag models
Posts::syncTags($post, ['eloquent', 'php']);   // or $post->syncTags([...])

// Filter
Post::query()->inCategory($laravel)->get();
Post::query()->inCategory('laravel')->get();   // by translated slug
Post::query()->withTag('php')->get();

// Category hierarchy helpers
$laravel->ancestors();    // [Guides]
$guides->descendants();   // [Laravel, …]

// A parent that would make the hierarchy loop is refused before anything is saved
$guides->update(['parent_id' => $laravel->id]);   // throws InvalidCategoryParentException
```

Category and tag `name` and `slug` are both translatable, and slugs are auto-generated from
the name. `inCategory()` / `withTag()` match a `Category`/`Tag` instance by its key. A slug
string matches at its best locale along the chain — the current locale's matches, else the
fallback locale's, else the next locale's — so a link shared from another language still
filters, but a different category that uses the same slug only in a later locale is never
pulled in.

### SEO meta and structured data

`meta_title` / `meta_description` are translatable; the remaining SEO fields live in a
non-translatable `seo` bag. Sensible fallbacks are applied (meta title → title, meta
description → perex), with the same locale fallback `$post->title` uses: the current locale's
meta value or title first, then the fallback locale's, then any locale's — so a post that only
has an English title still renders a `<title>` and a JSON-LD `headline` on a Slovak page:

```php
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;

Posts::seo($post, new SeoData(      // saves; $post->setSeo($data) only sets the attributes
    metaTitle: 'Custom title',
    canonical: 'https://example.test/posts/hello-world',
    ogImage: 'https://example.test/cover.png',
    robots: 'index,follow',
));

// In a Blade layout:
{!! $post->renderMetaTags() !!}   // <title>, description, canonical, og:*, twitter:*, robots
{!! $post->renderJsonLd() !!}     // <script type="application/ld+json"> BlogPosting/Article
                                  // (values hex-escaped: a stored `</script>` cannot break out)

$post->seo();        // SeoData with merged config defaults + fallbacks
$post->toJsonLd();   // array<string, mixed>
```

### Slugs

Posts, categories and tags get their slugs from
[`sluggable-for-laravel`](https://github.com/roundly-consulting/sluggable-for-laravel). Each keeps a
per-locale `slug` map:

- **Generated** from the title (posts, `posts.slugs.source`) or the name (categories, tags) for
  every locale that has one. A blank title, or one that slugifies to nothing, is skipped.
- **Filled, not rewritten**: a save that changes the model fills locales that have no slug yet;
  existing slugs stay put, so a title edit never breaks a URL. A no-op `save()` changes nothing —
  backfill with `php artisan sluggable:regenerate "RoundlyConsulting\Posts\Models\Post" --mode=missing`.
- **Unique per locale** (`posts.slugs.unique`): collisions get `-2`, `-3`, … in bounded, batched
  probes. Trashed rows and rows hidden by global scopes count as taken, and the per-locale unique
  indexes plus an automatic retry close the race between two concurrent saves.
- **Manual slugs** are normalised and made unique (`Custom Slug` → `custom-slug`, `taken` →
  `taken-2`), including the ones `CreatePostAction` receives.

```php
$post->currentSlug();        // current locale → posts.locales.fallback → any locale
$post->slugFor('sk');        // exactly one locale, no fallback
$post->slugMap();            // ['en' => 'hello-world', 'sk' => 'ahoj-svet']

// Swappable model: find through the facade, not Post::findBySlug() (which is always the packaged class)
Posts::findBySlug('hello-world');
Posts::query()->whereSlug('hello-world')->first();
```

**Route binding.** With `posts.slugs.route-binding` on (default), the slug is the post's route
key. `{post}` and `{post:slug}` bind by the current locale's slug, then the fallback locale, then
any locale, and `route('posts.show', $post)` generates the current-locale slug, so URLs and
binding always agree. A numeric slug is never shadowed by a post id.

```php
Route::get('/posts/{post}', fn (Post $post) => view('posts.show', compact('post')))->name('posts.show');
```

**URL safety.** Turn on `posts.slugs.history` (and publish `sluggable-migrations`) to remember
retired post slugs: a request for an old slug answers `301` to the current URL, query string kept.
On uuid/ulid posts (`posts.primary_key_type`), set `sluggable.key_type` to the same value before
migrating the history table.
Turn on `posts.slugs.lock-when-published` to freeze a post's slugs once it is published: no
automatic change, and a manual change throws `SlugLockedException`.

**Validation.** `Post::slugRules()` returns rules for a host form that accepts a slug map, using
the same uniqueness check as generation; pass the post being edited to ignore it:

```php
$request->validate([...Post::slugRules($post), 'title' => ['required', 'array']]);
```

Admins who should see a validation error instead of a silent `-2` can combine these rules with
sluggable's `Strict` manual policy in their own model subclass.

### Events

`PostPublished`, `PostScheduled`, `PostArchived`, `PostDrafted` (each carrying the `postId`)
are dispatched on the matching transition — listen for them to extend behaviour. They fire only
when the status actually changes (every `schedule()` fires `PostScheduled`), so a repeated call or
a racing `publishDue()` run never fires one twice.

### Status labels & select options

The package enum `PostStatus` uses the
[`roundly-consulting/enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel)
`Helpers` trait (pulled in automatically), so it exposes a readable, select-, and validation-ready
surface with no hand-rolled helpers:

```php
use RoundlyConsulting\Posts\Enums\PostStatus;

PostStatus::Published->label();   // 'Published' (readable, translatable via __())
PostStatus::labels();             // ['Draft', 'Scheduled', 'Published', 'Archived']
PostStatus::values();             // ['draft', 'scheduled', 'published', 'archived']

PostStatus::toOptions();          // ['draft' => 'Draft', ...] for a <select>
PostStatus::options();            // Collection<EnumOption{ value, label, name }> for JS/Inertia

PostStatus::validationRule();     // 'in:draft,scheduled,published,archived'
PostStatus::fromName('Published'); // PostStatus::Published
```

Use it directly in validation so the allow-list never drifts as cases change:

```php
$request->validate([
    'status' => ['required', PostStatus::validationRule()],
]);
```

Labels pass through Laravel's `__()` helper (headlined from the case value), so you localise them
in your app's translation files. See the
[enums-for-laravel README](https://github.com/roundly-consulting/enums-for-laravel) for the full
trait surface (lookups, equality checks, fluent conditionals, random selection).

### Media (integrates with media-library)

Posts builds on
[`roundly-consulting/media-library-for-laravel`](https://github.com/roundly-consulting/media-library-for-laravel)
(pulled in automatically — its service provider auto-discovers). The bundled `Post` owns three
media buckets via the `HasPostMedia` concern: a single-file **featured** image, a multi-file
**gallery**, and a **content** bucket holding the media referenced inline by the post body.

```php
use Illuminate\Http\UploadedFile;

// Featured image (single-file — a new attach replaces the previous one)
$post->addMedia($request->file('cover'))->toMediaBucket($post->featuredBucket());
$post->featuredImage();          // ?Media
$post->featuredImageUrl();       // string ('' or the configured fallback when empty)

// Gallery (multi-file, order preserved)
$post->addMedia($file)->toMediaBucket($post->galleryBucket());
$post->galleryImages();          // Collection<int, Media>
$post->galleryImageUrls();       // list<string>
```

**Inline media in content.** Drop `[media:UUID]` (or `[media:UUID|variant]`) tokens into the post
body, attach those files to the post's content bucket, then render. Images become responsive
`<img>` tags, other files become links; tokens resolve in a **single batched query** against the
post's **own** content media only (never an arbitrary global UUID), and a missing/unauthorized
token is silently stripped (or kept — see `posts.media.inline.on_missing`).

A variant — a token's `|variant` or `posts.media.inline.default_variant` — should be one
media-library generates for the content bucket (e.g. `responsive-640`). Variants are generated on
a queue, so until it exists (or when the name is unknown, say an author's typo) the image renders
from its original instead of failing the page. `featuredImageUrl($variant)` and
`galleryImageUrls($variant)` fall back the same way:

```php
$image = $post->addMedia($file)->toMediaBucket($post->contentBucket());
$post->setTranslation('content', 'en', "Intro [media:{$image->uuid}] outro")->save();

{!! $post->renderContent() !!}        // current locale
{!! $post->renderContent('sk') !!}    // a specific locale
```

> **Security — `renderContent()` returns raw, unescaped HTML.** The stored body is authored HTML
> and comes back verbatim (only the `[media:…]` tokens are replaced, with escaped values), as an
> `HtmlString` that Blade does not escape. Echo it with `{!! !!}` **only** when the body is trusted
> (written by trusted editors) or was sanitized when it was saved. If you accept rich text from
> untrusted users, sanitize it **at write time** with an allow-list HTML sanitizer of your choice —
> this package never sanitizes, neither on save nor on render.

**SEO fallback.** When a post has no explicit `og:image`, `seo()->ogImage` and the JSON-LD `image`
fall back to the featured image URL (toggle with `posts.media.seo_og_image`) — the
`posts.media.og_variant` variant once it is generated, the original until then.

**Warm variants on publish.** Publishing a post dispatches a queued media `GenerateVariantsJob`
for its featured/gallery/content media so responsive derivatives are ready when it goes live
(toggle with `posts.media.warm_on_publish`).

### Likes & reactions (integrates with likes-for-laravel)

The bundled `Post` implements `Likeable` via the `HasPostReactions` concern, so it builds on
[`likes-for-laravel`](https://github.com/roundly-consulting/likes-for-laravel). The **actor is
always passed explicitly** — posts never resolves the acting user from the auth guard.

```php
use RoundlyConsulting\Likes\Facades\Likes;

Likes::actor($user)->like($post);     // like / unlike / toggle
Likes::actor($user)->toggle($post);   // returns the resulting is-liked state
Likes::actor($user)->unlike($post);

$post->isLikedBy($user);              // bool
$post->likesCount();                  // int (uses an eager count when hydrated)
```

Popular / trending feeds and single-query per-viewer hydration compose as scopes:

```php
Post::published()->orderByLikesDesc()->limit(10)->get();   // "popular"
Post::published()->orderByTrending()->get();               // recency-weighted

$feed = Post::published()
    ->withLikesCount()              // hydrate likes_count
    ->withLikedState($viewer)       // hydrate is_liked + liked_reaction (pass the viewer)
    ->latest()
    ->paginate();
```

```blade
@foreach ($feed as $post)
    {{ $post->is_liked ? '♥' : '♡' }} {{ $post->likes_count }}
@endforeach
```

For API/Blade, `likeState()` returns a compact payload (guest viewer → `liked = false`):

```php
$post->likeState($viewer);
// => ['count' => 12, 'viewer_state' => ['liked' => true, 'reaction' => 'like'], 'breakdown' => ['like' => 12]]
```

Typed reactions (love/wow/…) are available by configuring `likes.reactions` on the host; see the
[likes-for-laravel README](https://github.com/roundly-consulting/likes-for-laravel).

### Reports & moderation (integrates with reports-for-laravel)

The bundled `Post` implements `Reportable` via the `HasPostReports` concern, building on
[`reports-for-laravel`](https://github.com/roundly-consulting/reports-for-laravel). The **reporter
is always passed explicitly**.

```php
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Facades\Reports;

Reports::report($post)
    ->by($user)
    ->for(Reason::Spam)
    ->because('Obvious spam.')
    ->create();

// Anonymous / guest report:
Reports::report($post)->asGuest(hash('sha256', $request->ip()))->for('spam')->create();
```

Dedup, the reason allowlist and reason labels are handled by reports; a duplicate report throws
`DuplicateReportException`, an unknown reason throws `UnknownReportReasonException`.

Build a "needs review" queue straight off the `Post` query:

```php
$post->hasBeenReported();                        // bool
$post->isReportedBy($user);                      // bool
$post->reportsCount(Status::Pending);            // int

Post::query()->withReportCounts()->get();        // eager reports_count
Post::query()->mostReported()->paginate();       // by total report count
Post::query()->reportedMoreThan(5)->get();       // over a threshold
```

**Multi-moderator sign-off.** Because reports routes resolution through
[`approvals-for-laravel`](https://github.com/roundly-consulting/approvals-for-laravel), a report can
require N moderators to agree before it settles. Moderators are saved Eloquent models — typically
your `User` with the approvals `GivesApprovals` trait (which adds its `givenApprovals()` relation):

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Approvals\Traits\GivesApprovals;

class User extends Model implements GivesApprovalsInterface
{
    use GivesApprovals;
}
```

```php
use RoundlyConsulting\Approvals\Enums\ApprovalRule;

Reports::moderate($report)->requiring([$alice, $bob])->rule(ApprovalRule::Quorum)->quorum(2)->open();

Reports::resolve($report, by: $alice);              // 1 of 2 — stays open
Reports::resolve($report, by: $bob, note: 'Spam');  // quorum reached → Resolved → post archived

Reports::resolve($report, by: $mallory);            // not named → ModeratorRequiredException
```

Only the moderators the request names (or their delegates) can settle it: anyone else — and a
`resolve()` with no actor — gets reports' `ModeratorRequiredException`, nothing is recorded, and
the post stays as it is.

**Moderation → visibility sync.** When a report is **upheld** (`ReportResolved`) or a post crosses
the global `reports.threshold` (`ReportThresholdReached`), the post is auto-unpublished through its
own lifecycle (`$post->archive()` / `$post->unpublish()`, so `Posts::fake()` records it),
re-emitting `PostArchived` / `PostDrafted`. This is config-gated by `posts.moderation` and touches
a post that is published **or scheduled** — a scheduled post is taken off the schedule, so
`publishDue()` never puts moderated content live. Drafts and archived posts are left alone
(idempotent), and any non-post report subject is ignored:

```php
'moderation' => [
    'on_resolved' => 'archive',   // 'archive' | 'draft' | null (disable the upheld-report path)
    'auto_unpublish' => true,     // auto-archive on ReportThresholdReached
],
```

Set both to `null` / `false` to disable auto-moderation entirely and drive visibility yourself.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

Please see [CONTRIBUTING](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=posts-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=posts-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
