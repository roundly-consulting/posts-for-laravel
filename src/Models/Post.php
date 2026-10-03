<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use RoundlyConsulting\Likes\Contracts\Likeable;
use RoundlyConsulting\MediaLibrary\Contracts\HasMedia;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Posts\Concerns\HasConfigurableKey;
use RoundlyConsulting\Posts\Concerns\HasPostMedia;
use RoundlyConsulting\Posts\Concerns\HasPostReactions;
use RoundlyConsulting\Posts\Concerns\HasPostReports;
use RoundlyConsulting\Posts\Concerns\HasTranslatableAttributes;
use RoundlyConsulting\Posts\Database\Factories\PostFactory;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\PostsManager;
use RoundlyConsulting\Posts\Support\JsonLdBuilder;
use RoundlyConsulting\Posts\Support\PostModel;
use RoundlyConsulting\Posts\Support\PostSlugs;
use RoundlyConsulting\Reports\Contracts\Reportable;
use RoundlyConsulting\Sluggable\Concerns\HasSlug;
use RoundlyConsulting\Sluggable\Contracts\Sluggable;
use RoundlyConsulting\Sluggable\Contracts\SlugLocales;
use RoundlyConsulting\Sluggable\Definitions\SlugOptions;
use RoundlyConsulting\Sluggable\Rules\UniqueSlug;

/**
 * @property int|string $id
 * @property PostStatus $status
 * @property CarbonImmutable|null $published_at
 * @property array<string, string> $title
 * @property array<string, string> $slug
 * @property array<string, string> $perex
 * @property array<string, string> $content
 * @property array<string, string> $meta_title
 * @property array<string, string> $meta_description
 * @property array<string, mixed>|null $seo
 * @property string|null $author_type
 * @property int|string|null $author_id
 * @property Model|null $author
 * @property Collection<int, Category> $categories
 * @property Collection<int, Tag> $tags
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 *
 * Deliberately not `final`: `config('posts.model')` documents pointing the package
 * at your own subclass, which `final` would make impossible.
 */
class Post extends Model implements HasMedia, Likeable, Reportable, Sluggable
{
    // Both define `resolveRouteBindingQuery()`: sluggable's handles the slug field and keeps
    // the uuid/ulid malformed-id 404 for key fields, so it wins.
    use HasConfigurableKey, HasSlug {
        HasSlug::resolveRouteBindingQuery insteadof HasConfigurableKey;
    }

    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use HasPostMedia;
    use HasPostReactions;
    use HasPostReports;
    use HasTranslatableAttributes;
    use SoftDeletes;

    protected $guarded = [];

    /** @var list<string> */
    public array $translatable = [
        'title',
        'slug',
        'perex',
        'content',
        'meta_title',
        'meta_description',
    ];

    public function getTable(): string
    {
        return (string) config('posts.tables.posts', 'posts');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'immutable_datetime',
            'seo' => 'array',
            'title' => 'array',
            'slug' => 'array',
            'perex' => 'array',
            'content' => 'array',
            'meta_title' => 'array',
            'meta_description' => 'array',
        ];
    }

    /**
     * Store `published_at` as its instant in the app timezone, whatever zone the given date
     * carries. `published()` and `publishDue()` compare the column against `now()`, so a date
     * stored as another zone's wall-clock time (`now('Asia/Tokyo')->addHour()` as `22:00` on a
     * UTC app) would go live hours late — or early, west of the app's zone.
     *
     * @return Attribute<CarbonImmutable|null, mixed>
     */
    protected function publishedAt(): Attribute
    {
        return Attribute::set(fn (mixed $value): mixed => $value === null || $value === ''
            ? null
            : $this->fromDateTime($this->asDateTime($value)->setTimezone(date_default_timezone_get())));
    }

    /** @return MorphTo<Model, $this> */
    public function author(): MorphTo
    {
        return $this->morphTo((string) config('posts.author.morph-name', 'author'));
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            (string) config('posts.tables.category_post', 'category_post'),
            'post_id',
            'category_id',
        )->withTimestamps();
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            (string) config('posts.tables.tag_post', 'post_tag'),
            'post_id',
            'tag_id',
        )->withTimestamps();
    }

    /** @param  Builder<Post>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->where('published_at', '<=', now());
    }

    /** @param  Builder<Post>  $query */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', PostStatus::Draft);
    }

    /** @param  Builder<Post>  $query */
    public function scopeScheduled(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->where('status', PostStatus::Scheduled)
                ->orWhere(function (Builder $query): void {
                    $query->where('status', PostStatus::Published)
                        ->where('published_at', '>', now());
                });
        });
    }

    /** @param  Builder<Post>  $query */
    public function scopeArchived(Builder $query): void
    {
        $query->where('status', PostStatus::Archived);
    }

    /**
     * Posts in a category. An instance matches by its key. A slug matches the categories it
     * names at its best locale along sluggable's chain — the current locale's matches, else the
     * fallback's, else the next locale's (the level `{category}` binding prefers) — never a
     * category that only uses the same slug in a later locale of the chain.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeInCategory(Builder $query, Category|string $category): void
    {
        $query->whereHas('categories', static fn (Builder $query): Builder => $category instanceof Category
            ? $query->whereKey($category->getKey())
            : self::whereBestSlugMatch($query, Category::query(), $category));
    }

    /**
     * Posts carrying a tag — an instance by its key, a slug by the tags it names at its best
     * locale along the chain (see {@see scopeInCategory()}).
     *
     * @param  Builder<Post>  $query
     */
    public function scopeWithTag(Builder $query, Tag|string $tag): void
    {
        $query->whereHas('tags', static fn (Builder $query): Builder => $tag instanceof Tag
            ? $query->whereKey($tag->getKey())
            : self::whereBestSlugMatch($query, Tag::query(), $tag));
    }

    /**
     * Publish the post at `$at` (now when omitted). Routes through {@see PostsManager::publish()},
     * so a faked manager records it; override it in a `posts.model` subclass to hook in.
     */
    public function publish(?CarbonInterface $at = null): self
    {
        app(PostsManager::class)->publish($this, $at);

        return $this;
    }

    /**
     * Schedule the post to go live at `$at` — {@see PostsManager::schedule()}.
     */
    public function schedule(CarbonInterface $at): self
    {
        app(PostsManager::class)->schedule($this, $at);

        return $this;
    }

    /**
     * Archive the post — {@see PostsManager::archive()}.
     */
    public function archive(): self
    {
        app(PostsManager::class)->archive($this);

        return $this;
    }

    /**
     * Move the post back to draft — {@see PostsManager::unpublish()}.
     */
    public function unpublish(): self
    {
        app(PostsManager::class)->unpublish($this);

        return $this;
    }

    /**
     * Sync the post's tags: `Tag` models, or names found or created in the current locale —
     * {@see PostsManager::syncTags()}.
     *
     * @param  iterable<int, string|Tag>  $tags
     */
    public function syncTags(iterable $tags): self
    {
        app(PostsManager::class)->syncTags($this, $tags);

        return $this;
    }

    public function seo(): SeoData
    {
        $bag = is_array($this->seo) ? $this->seo : [];

        $metaTitle = $this->seoText('meta_title', 'title');
        $metaDescription = $this->seoText('meta_description', 'perex');

        $base = SeoData::fromBag($bag, $metaTitle, $metaDescription);

        return new SeoData(
            metaTitle: $base->metaTitle,
            metaDescription: $base->metaDescription,
            canonical: $base->canonical,
            ogTitle: $base->ogTitle ?? $base->metaTitle,
            ogDescription: $base->ogDescription ?? $base->metaDescription,
            ogImage: $base->ogImage ?? $this->fallbackOgImage(),
            ogType: $base->ogType ?? 'article',
            twitterCard: $base->twitterCard ?? (string) config('posts.seo.default-card', 'summary_large_image'),
            twitterSite: $base->twitterSite ?? self::stringConfig('posts.seo.twitter-site'),
            twitterCreator: $base->twitterCreator,
            robots: $base->robots ?? (string) config('posts.seo.default-robots', 'index,follow'),
            ogSiteName: $base->ogSiteName ?? self::stringConfig('posts.seo.site-name'),
        );
    }

    public function setSeo(SeoData $data): self
    {
        $locale = app()->getLocale();

        if ($data->metaTitle !== null) {
            $this->setTranslation('meta_title', $locale, $data->metaTitle);
        }

        if ($data->metaDescription !== null) {
            $this->setTranslation('meta_description', $locale, $data->metaDescription);
        }

        $this->seo = $data->toBag();

        return $this;
    }

    public function renderMetaTags(): HtmlString
    {
        return new HtmlString(
            View::make('posts::meta', ['seo' => $this->seo()])->render(),
        );
    }

    /** @return array<string, mixed> */
    public function toJsonLd(): array
    {
        return app(JsonLdBuilder::class)->build($this);
    }

    public function renderJsonLd(): HtmlString
    {
        return new HtmlString(
            View::make('posts::json-ld', ['data' => $this->toJsonLd()])->render(),
        );
    }

    /**
     * The slug definition — per-locale, generated from `posts.slugs.source`, unique per locale
     * (DB-indexed), bound by the current-locale slug. See {@see PostSlugs}.
     */
    public function slugOptions(): SlugOptions
    {
        return SlugOptions::make(
            PostSlugs::definition((string) config('posts.slugs.source', 'title'))
                ->keepHistory(Config::boolean('posts.slugs.history'))
                ->lockWhen(fn (Post $post): bool => Config::boolean('posts.slugs.lock-when-published')
                    && $post->status === PostStatus::Published)
                ->routeKey(Config::boolean('posts.slugs.route-binding', true)),
        );
    }

    /**
     * Validation rules for a host form that accepts a slug map (`slug.en`, `slug.sk`, …):
     * the same uniqueness check generation uses. Pass the post being edited to ignore it.
     *
     * @return array<string, list<mixed>>
     */
    public static function slugRules(?Post $ignore = null): array
    {
        return ['slug' => ['nullable', 'array', UniqueSlug::for(PostModel::class())->ignore($ignore)]];
    }

    /**
     * Constrain a category/tag query to the rows a slug names at the first locale of the slug
     * chain that has any match: level N is "matches in locale N and in no earlier locale", so a
     * row that shares the slug in a later locale never widens an earlier match, while every row
     * of the winning level is kept (several, when `posts.slugs.unique` is off). Predicates only —
     * no ORDER BY, so counts over the `whereHas` stay valid SQL on every engine.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  Builder<Category>|Builder<Tag>  $taxonomy  a fresh query on the same model
     * @return Builder<TModel>
     */
    private static function whereBestSlugMatch(Builder $query, Builder $taxonomy, string $slug): Builder
    {
        $model = $taxonomy->getModel();
        $key = $query->getModel()->getQualifiedKeyName();
        $locales = $model->slugDefinition()->chain(app(SlugLocales::class));

        return $query->where(static function (Builder $query) use ($taxonomy, $model, $key, $locales, $slug): void {
            $earlier = [];

            foreach ($locales as $locale) {
                $level = (clone $taxonomy)->whereSlug($slug, locale: $locale);

                $query->orWhere(static function (Builder $query) use ($model, $key, $level, $earlier): void {
                    $query->whereIn($key, (clone $level)->select($model->getQualifiedKeyName()));

                    foreach ($earlier as $previous) {
                        $query->whereNotExists($previous->toBase());
                    }
                });

                $earlier[] = $level;
            }
        });
    }

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    /**
     * A meta field with the same locale fallback `$post->title` uses: the current locale's meta
     * value, else its `$content` value (meta title → title), then the same pair along the
     * fallback chain (the fallback locale, then the lowest-sorting locale holding a value).
     */
    private function seoText(string $meta, string $content): ?string
    {
        $locale = app()->getLocale();

        return $this->getTranslation($meta, $locale, false)
            ?: $this->getTranslation($content, $locale, false)
            ?: $this->getTranslation($meta, $locale)
            ?: $this->getTranslation($content, $locale)
            ?: null;
    }

    /** The featured image URL used as the og:image fallback, or null when unavailable/disabled. */
    private function fallbackOgImage(): ?string
    {
        if (! Config::boolean('posts.media.seo_og_image', true)) {
            return null;
        }

        $url = $this->featuredImageUrl((string) config('posts.media.og_variant', ''));

        return $url !== '' ? $url : null;
    }

    private static function stringConfig(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
