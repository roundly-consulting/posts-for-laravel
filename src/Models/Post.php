<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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
use RoundlyConsulting\Posts\Concerns\HasConfigurableKey;
use RoundlyConsulting\Posts\Concerns\HasPostMedia;
use RoundlyConsulting\Posts\Concerns\HasPostReactions;
use RoundlyConsulting\Posts\Concerns\HasPostReports;
use RoundlyConsulting\Posts\Concerns\HasTranslatableAttributes;
use RoundlyConsulting\Posts\Database\Factories\PostFactory;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostArchived;
use RoundlyConsulting\Posts\Events\PostDrafted;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Events\PostScheduled;
use RoundlyConsulting\Posts\Exceptions\InvalidPostStatusTransitionException;
use RoundlyConsulting\Posts\Support\JsonLdBuilder;
use RoundlyConsulting\Posts\Support\PostModel;
use RoundlyConsulting\Posts\Support\PostSlugs;
use RoundlyConsulting\Reports\Contracts\Reportable;
use RoundlyConsulting\Sluggable\Concerns\HasSlug;
use RoundlyConsulting\Sluggable\Contracts\Sluggable;
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
     * Posts in a category, matched by the category's slug along sluggable's locale chain
     * (current → fallback → any locale).
     *
     * @param  Builder<Post>  $query
     */
    public function scopeInCategory(Builder $query, Category|string $category): void
    {
        $slug = $category instanceof Category ? (string) $category->currentSlug() : $category;

        $match = Category::query()->whereSlug($slug);

        $query->whereHas('categories', static fn (Builder $query): Builder => $query->mergeConstraintsFrom($match));
    }

    /**
     * Posts carrying a tag, matched by the tag's slug along sluggable's locale chain.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeWithTag(Builder $query, Tag|string $tag): void
    {
        $slug = $tag instanceof Tag ? (string) $tag->currentSlug() : $tag;

        $match = Tag::query()->whereSlug($slug);

        $query->whereHas('tags', static fn (Builder $query): Builder => $query->mergeConstraintsFrom($match));
    }

    public function publish(?CarbonInterface $at = null): self
    {
        $this->transitionTo(PostStatus::Published, $at ?? now());

        PostPublished::dispatch($this->id);

        return $this;
    }

    public function schedule(CarbonInterface $at): self
    {
        $this->transitionTo(PostStatus::Scheduled, $at);

        PostScheduled::dispatch($this->id);

        return $this;
    }

    public function archive(): self
    {
        $this->transitionTo(PostStatus::Archived, $this->published_at);

        PostArchived::dispatch($this->id);

        return $this;
    }

    public function draft(): self
    {
        $this->transitionTo(PostStatus::Draft, null);

        PostDrafted::dispatch($this->id);

        return $this;
    }

    /**
     * Find-or-create tags by their translated name in the current locale and
     * sync them onto the post.
     *
     * @param  iterable<int, string|Tag>  $tags
     */
    public function syncTags(iterable $tags): self
    {
        $locale = app()->getLocale();
        $ids = [];

        foreach ($tags as $tag) {
            if ($tag instanceof Tag) {
                $ids[] = $tag->getKey();

                continue;
            }

            $existing = Tag::query()
                ->where(function (Builder $query) use ($locale, $tag): void {
                    $query->getQuery()->where("name->{$locale}", $tag);
                })
                ->first();

            $ids[] = $existing?->getKey() ?? Tag::create([
                'name' => [$locale => $tag],
            ])->getKey();
        }

        $this->tags()->sync($ids);

        return $this;
    }

    public function seo(): SeoData
    {
        $bag = is_array($this->seo) ? $this->seo : [];

        $metaTitle = $this->getTranslation('meta_title', app()->getLocale(), false)
            ?: $this->getTranslation('title', app()->getLocale(), false)
            ?: null;

        $metaDescription = $this->getTranslation('meta_description', app()->getLocale(), false)
            ?: $this->getTranslation('perex', app()->getLocale(), false)
            ?: null;

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
                ->keepHistory((bool) config('posts.slugs.history', false))
                ->lockWhen(fn (Post $post): bool => (bool) config('posts.slugs.lock-when-published', false)
                    && $post->status === PostStatus::Published)
                ->routeKey((bool) config('posts.slugs.route-binding', true)),
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

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    private function transitionTo(PostStatus $status, ?CarbonInterface $publishedAt): void
    {
        if ($this->status === PostStatus::Archived && $status !== PostStatus::Draft && $status !== PostStatus::Archived) {
            throw InvalidPostStatusTransitionException::between($this->status, $status);
        }

        $this->status = $status;
        $this->published_at = $publishedAt !== null ? CarbonImmutable::instance($publishedAt) : null;
        $this->save();
    }

    /** The featured image URL used as the og:image fallback, or null when unavailable/disabled. */
    private function fallbackOgImage(): ?string
    {
        if (! (bool) config('posts.media.seo_og_image', true)) {
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
