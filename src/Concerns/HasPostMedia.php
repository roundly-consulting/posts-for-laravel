<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use RoundlyConsulting\MediaLibrary\Buckets\MediaBucket;
use RoundlyConsulting\MediaLibrary\Concerns\InteractsWithMedia;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\Posts\Support\ContentMediaRenderer;

/**
 * First-class media for the bundled Post model, built on
 * roundly-consulting/media-library-for-laravel.
 *
 * Declares the post's `featured` (single-file), `gallery` (multi-file) and `content` buckets and
 * adds post-specific readers on top of media-library's `InteractsWithMedia` seam: the featured
 * image, the gallery, and an inline `[media:UUID]` content renderer.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 * @mixin \RoundlyConsulting\Posts\Concerns\HasTranslatableAttributes
 */
trait HasPostMedia
{
    use InteractsWithMedia;

    /** Web image formats accepted by the featured/gallery buckets. */
    private const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    public function registerMediaBuckets(): void
    {
        $featured = $this->configureMediaBucket(
            $this->addMediaBucket($this->featuredBucket())
                ->singleFile()
                ->acceptsMimeTypes(self::IMAGE_MIME_TYPES),
        );

        $fallback = config('posts.media.featured_fallback_url');

        if (is_string($fallback) && $fallback !== '') {
            $featured->useFallbackUrl($fallback);
        }

        $this->configureMediaBucket(
            $this->addMediaBucket($this->galleryBucket())
                ->acceptsMimeTypes(self::IMAGE_MIME_TYPES),
        );

        // The content bucket holds inline media of any type (images render responsively,
        // other files render as links), so it stays mime-unrestricted.
        $this->configureMediaBucket(
            $this->addMediaBucket($this->contentBucket()),
        );
    }

    public function featuredImage(): ?Media
    {
        return $this->getFirstMedia($this->featuredBucket());
    }

    public function featuredImageUrl(string $variant = ''): string
    {
        return $this->getFirstMediaUrl($this->featuredBucket(), $variant);
    }

    /** @return Collection<int, Media> */
    public function galleryImages(): Collection
    {
        return $this->getMedia($this->galleryBucket());
    }

    /** @return list<string> */
    public function galleryImageUrls(string $variant = ''): array
    {
        return array_values(
            $this->galleryImages()
                ->map(fn (Media $media): string => $media->getUrl($variant))
                ->all(),
        );
    }

    /**
     * Render the post body for a locale, expanding inline `[media:UUID]` / `[media:UUID|variant]`
     * tokens into responsive images / links from the post's own content bucket.
     *
     * Resolution is a single batched query over the referenced UUIDs and never throws on a
     * missing/unauthorized UUID (it is stripped or kept per `posts.media.inline.on_missing`).
     */
    public function renderContent(?string $locale = null): HtmlString
    {
        $locale ??= app()->getLocale();
        $content = $this->getTranslation('content', $locale);

        if (! (bool) config('posts.media.inline.enabled', true)) {
            return new HtmlString($content);
        }

        $renderer = app(ContentMediaRenderer::class);
        $uuids = $renderer->extractUuids($content);

        /** @var Collection<int, Media> $media */
        $media = $uuids === []
            ? new Collection
            : $this->media()
                ->where('bucket_name', $this->contentBucket())
                ->whereIn('uuid', $uuids)
                ->get()
                // The media is owned by this post; hydrate the inverse relation so URL/path
                // generation does not lazily reload the owner once per token (avoids N+1).
                ->each(fn (Media $media): Media => $media->setRelation('model', $this));

        $rendered = $renderer->render(
            $content,
            $media,
            (string) config('posts.media.inline.default_variant', ''),
            $this->onMissingStrategy(),
        );

        return new HtmlString($rendered);
    }

    public function featuredBucket(): string
    {
        return (string) config('posts.media.featured_bucket', 'featured');
    }

    public function galleryBucket(): string
    {
        return (string) config('posts.media.gallery_bucket', 'gallery');
    }

    public function contentBucket(): string
    {
        return (string) config('posts.media.content_bucket', 'content');
    }

    private function configureMediaBucket(MediaBucket $bucket): MediaBucket
    {
        $disk = config('posts.media.disk');

        if (is_string($disk) && $disk !== '') {
            $bucket->useDisk($disk);
        }

        $widths = config('posts.media.responsive_widths');

        $bucket->responsiveWidths(
            is_array($widths) ? $this->normalizeWidths($widths) : null,
        );

        return $bucket;
    }

    /**
     * @param  array<array-key, mixed>  $widths
     * @return list<int>
     */
    private function normalizeWidths(array $widths): array
    {
        $clean = [];

        foreach ($widths as $width) {
            if (is_int($width) && $width > 0) {
                $clean[] = $width;
            }
        }

        return $clean;
    }

    private function onMissingStrategy(): string
    {
        $strategy = config('posts.media.inline.on_missing', 'strip');

        return $strategy === 'keep' ? 'keep' : 'strip';
    }
}
