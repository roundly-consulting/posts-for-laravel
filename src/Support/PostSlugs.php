<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Support;

use RoundlyConsulting\Sluggable\Definitions\SlugDefinition;
use RoundlyConsulting\Sluggable\Enums\EmptySourcePolicy;
use RoundlyConsulting\Sluggable\Enums\LocaleFallback;
use RoundlyConsulting\Sluggable\Enums\ManualSlugPolicy;
use RoundlyConsulting\Sluggable\Enums\UpdatePolicy;

/**
 * The slug definition posts, categories and tags share: a per-locale `slug` map generated
 * from a translatable source, filled for missing locales on every dirty save, and — while
 * `posts.slugs.unique` is on — unique per locale against every row, trashed ones included,
 * which is exactly what the per-locale unique indexes in the create migrations enforce.
 *
 * Uniqueness, trashed visibility and per-locale scope are pinned here rather than left to
 * `sluggable.defaults.*`: the migrations build indexes of this exact shape, and a host
 * default drifting away from them would make the definition and the index disagree.
 */
final class PostSlugs
{
    public static function definition(string $source): SlugDefinition
    {
        $definition = SlugDefinition::for('slug')
            ->from($source)
            ->separator((string) config('posts.slugs.separator', '-'))
            ->localized()
            ->onUpdate(UpdatePolicy::IfEmpty)
            ->manual(ManualSlugPolicy::Normalize)
            ->whenEmptySource(EmptySourcePolicy::Skip)
            ->fallbackLocale(static fn (): string => (string) config('posts.locales.fallback', 'en'))
            ->fallback(LocaleFallback::Any)
            ->perLocaleUniqueness()
            ->includeTrashed();

        return (bool) config('posts.slugs.unique', true) ? $definition->unique() : $definition->notUnique();
    }
}
