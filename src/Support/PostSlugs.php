<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Support;

use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Sluggable\DataTransferObjects\SlugIndexSpec;
use RoundlyConsulting\Sluggable\Definitions\SlugDefinition;
use RoundlyConsulting\Sluggable\Enums\EmptySourcePolicy;
use RoundlyConsulting\Sluggable\Enums\LocaleFallback;
use RoundlyConsulting\Sluggable\Enums\ManualSlugPolicy;
use RoundlyConsulting\Sluggable\Enums\UpdatePolicy;
use RoundlyConsulting\Sluggable\Exceptions\UnsupportedDriverException;
use RoundlyConsulting\Sluggable\Schema\SlugIndexes;

/**
 * The slug definition posts, categories and tags share: a per-locale `slug` map generated
 * from a translatable source, filled for missing locales on every dirty save, and — while
 * `posts.slugs.unique` is on — unique per locale against every row, trashed ones included,
 * which is exactly what the per-locale unique indexes in the create migrations enforce.
 *
 * Uniqueness, trashed visibility and per-locale scope are pinned here rather than left to
 * `sluggable.defaults.*`: the migrations build indexes of this exact shape, and a host
 * default drifting away from them would make the definition and the index disagree.
 *
 * @internal
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

        return Config::boolean('posts.slugs.unique', true) ? $definition->unique() : $definition->notUnique();
    }

    /**
     * The create migrations' slug indexes: one unique index per supported locale on `slug`,
     * trashed rows included — the shape {@see definition()} probes — while `posts.slugs.unique`
     * is on. On an engine sluggable has no index form for (SQL Server) uniqueness stays
     * application-level, as it was before the indexes existed, instead of aborting the install;
     * sluggable picks its driver before it runs any DDL, so nothing is half-built.
     */
    public static function ensureIndexes(string $table): void
    {
        if (! Config::boolean('posts.slugs.unique', true)) {
            return;
        }

        try {
            SlugIndexes::ensure(SlugIndexSpec::localeMap($table, 'slug'));
        } catch (UnsupportedDriverException) {
            // No per-locale index on this engine; generation and validation still probe.
        }
    }
}
