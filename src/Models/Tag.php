<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Posts\Concerns\HasConfigurableKey;
use RoundlyConsulting\Posts\Concerns\HasSluggableTranslations;
use RoundlyConsulting\Posts\Concerns\HasTranslatableAttributes;
use RoundlyConsulting\Posts\Database\Factories\TagFactory;
use RoundlyConsulting\Posts\Support\PostModel;

/**
 * @property int|string $id
 * @property array<string, string> $name
 * @property array<string, string> $slug
 * @property Collection<int, Post> $posts
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
final class Tag extends Model
{
    use HasConfigurableKey;

    /** @use HasFactory<TagFactory> */
    use HasFactory;
    use HasSluggableTranslations;
    use HasTranslatableAttributes;
    use SoftDeletes;

    protected $guarded = [];

    /** @var list<string> */
    public array $translatable = ['name', 'slug'];

    public function getTable(): string
    {
        return (string) config('posts.tables.tags', 'post_tags');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'slug' => 'array',
        ];
    }

    /** @return BelongsToMany<Post, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            PostModel::class(),
            (string) config('posts.tables.tag_post', 'post_tag'),
            'tag_id',
            'post_id',
        )->withTimestamps();
    }

    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
    }

    public function slugSource(): string
    {
        return 'name';
    }
}
