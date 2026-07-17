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
use RoundlyConsulting\Posts\Concerns\HasSluggableTranslations;
use RoundlyConsulting\Posts\Concerns\HasTranslatableAttributes;
use RoundlyConsulting\Posts\Database\Factories\CategoryFactory;
use RoundlyConsulting\Posts\Support\PostModel;

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
final class Category extends Model
{
    use HasConfigurableKey;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;
    use HasSluggableTranslations;
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

    /** @return Collection<int, Category> */
    public function ancestors(): Collection
    {
        /** @var Collection<int, Category> $ancestors */
        $ancestors = new Collection;
        $parent = $this->parent;

        while ($parent !== null) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /** @return Collection<int, Category> */
    public function descendants(): Collection
    {
        /** @var Collection<int, Category> $descendants */
        $descendants = new Collection;

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->descendants());
        }

        return $descendants;
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    public function slugSource(): string
    {
        return 'name';
    }
}
