<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Posts\Concerns\HasConfigurableKey;
use RoundlyConsulting\Posts\Concerns\HasTranslatableAttributes;
use RoundlyConsulting\Posts\Database\Factories\CategoryFactory;
use RoundlyConsulting\Posts\Exceptions\InvalidCategoryParentException;
use RoundlyConsulting\Posts\Support\PostModel;
use RoundlyConsulting\Posts\Support\PostSlugs;
use RoundlyConsulting\Sluggable\Concerns\HasSlug;
use RoundlyConsulting\Sluggable\Contracts\Sluggable;
use RoundlyConsulting\Sluggable\Definitions\SlugOptions;

/**
 * @property int|string $id
 * @property int|string|null $parent_id
 * @property array<string, string> $name
 * @property array<string, string> $slug
 * @property int $position
 * @property Category|null $parent
 * @property Collection<int, Category> $children
 * @property Collection<int, Post> $posts
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
final class Category extends Model implements Sluggable
{
    // Both define `resolveRouteBindingQuery()`; sluggable's keeps the key-field behaviour.
    use HasConfigurableKey, HasSlug {
        HasSlug::resolveRouteBindingQuery insteadof HasConfigurableKey;
    }

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasTranslatableAttributes;
    use SoftDeletes;

    protected $guarded = [];

    /** @var list<string> */
    public array $translatable = ['name', 'slug'];

    public function getTable(): string
    {
        return (string) config('posts.tables.categories', 'post_categories');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'name' => 'array',
            'slug' => 'array',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsToMany<Post, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            PostModel::class(),
            (string) config('posts.tables.category_post', 'category_post'),
            'category_id',
            'post_id',
        )->withTimestamps();
    }

    /**
     * Refuse a parent that would make the hierarchy loop — the category itself or one of its
     * descendants — before anything is written.
     */
    protected static function booted(): void
    {
        self::saving(static function (Category $category): void {
            $category->guardAgainstCycle();
        });
    }

    /**
     * The chain of parents, nearest first. Stops at a category it has already seen, so a cycle
     * written past the model (a raw update) still terminates.
     *
     * @return Collection<int, Category>
     */
    public function ancestors(): Collection
    {
        /** @var Collection<int, Category> $ancestors */
        $ancestors = new Collection;
        $seen = [$this->visitKey($this) => true];
        $parent = $this->parent;

        while ($parent !== null && ! isset($seen[$this->visitKey($parent)])) {
            $seen[$this->visitKey($parent)] = true;
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Every category below this one, depth first. Each is listed once and the walk never comes
     * back up to this category, so a cycle written past the model still terminates.
     *
     * @return Collection<int, Category>
     */
    public function descendants(): Collection
    {
        /** @var Collection<int, Category> $descendants */
        $descendants = new Collection;
        $seen = [$this->visitKey($this) => true];

        $this->collectDescendants($this, $descendants, $seen);

        return $descendants;
    }

    /**
     * @param  Collection<int, Category>  $descendants
     * @param  array<string, true>  $seen
     */
    private function collectDescendants(Category $category, Collection $descendants, array &$seen): void
    {
        foreach ($category->children as $child) {
            $key = $this->visitKey($child);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $descendants->push($child);
            $this->collectDescendants($child, $descendants, $seen);
        }
    }

    /**
     * Walk up from the new parent, trashed rows included (a restore would close the loop): a
     * cycle exists when the walk reaches this category, or a category it has already passed.
     *
     * @throws InvalidCategoryParentException
     */
    private function guardAgainstCycle(): void
    {
        $parentId = $this->parent_id;

        if ($parentId === null || ! $this->isDirty('parent_id')) {
            return;
        }

        $self = $this->getKey();
        $seen = [];
        $current = $parentId;

        while ($current !== null) {
            if ((string) $current === (string) $self || isset($seen[(string) $current])) {
                throw InvalidCategoryParentException::wouldCycle($this, $parentId);
            }

            $seen[(string) $current] = true;

            $next = self::withTrashed()->whereKey($current)->value('parent_id');
            $current = is_int($next) || is_string($next) ? $next : null;
        }
    }

    private function visitKey(Category $category): string
    {
        $key = $category->getKey();

        return is_int($key) || is_string($key) ? (string) $key : spl_object_hash($category);
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    /** Per-locale slugs from `name`; see {@see PostSlugs}. */
    public function slugOptions(): SlugOptions
    {
        return SlugOptions::make(PostSlugs::definition('name'));
    }
}
