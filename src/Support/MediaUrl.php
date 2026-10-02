<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Support;

use RoundlyConsulting\MediaLibrary\Models\Media;

/**
 * A media URL for a variant that never throws: the variant's URL once it has been generated,
 * else the original's. media-library throws `InvalidVariant` for a variant that is unknown or
 * not generated yet, and variants are warmed on a queue — so without this a post rendered
 * between publish and the worker (or carrying an author's `|typo`) would fail outright.
 *
 * @internal
 */
final class MediaUrl
{
    public static function of(Media $media, string $variant = ''): string
    {
        return $variant !== '' && $media->hasGeneratedVariant($variant)
            ? $media->getUrl($variant)
            : $media->getUrl();
    }
}
