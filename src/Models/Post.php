<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Posts\Database\Factories\PostFactory;
use Acme\Image\Enums\Fit;
use Acme\MediaLibrary\HasMedia;
use Acme\MediaLibrary\InteractsWithMedia;
use Acme\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property bool $visible
 * @property string|null $content
 * @property int|string|null $author_id
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 */
final class Post extends Model implements HasMedia
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithMedia;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Model, $this> */
    public function author(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('posts.author-model');

        return $this->belongsTo(related: $model, foreignKey: 'author_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('default')
            ->useDisk(config('posts.disk'))
            ->withResponsiveImagesIf(config('posts.responsive-images'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $conversion = $this->addMediaConversion('preview');

        $conversion = config('posts.queue-file-conversions')
            ? $conversion->queued()
            : $conversion->nonQueued();

        $conversion->fit(Fit::Crop, 250, 250)->sharpen(10);
    }

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }
}
