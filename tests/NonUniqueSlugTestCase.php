<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests;

/**
 * `posts.slugs.unique` switched off BEFORE the migrations run — the only window in which it
 * decides whether the per-locale unique indexes are built. Toggling it inside a test on the
 * default case would leave the indexes in place and prove nothing about the install a host
 * actually gets.
 */
abstract class NonUniqueSlugTestCase extends TestCase
{
    /** @return array<string, mixed> */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'posts.slugs.unique' => false,
        ]);
    }
}
