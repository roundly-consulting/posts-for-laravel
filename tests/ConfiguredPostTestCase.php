<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests;

use RoundlyConsulting\Posts\Tests\Support\CustomPost;

/**
 * Boots the app with a host post model configured BEFORE the provider boots, so the
 * route-model binding and the event listeners are wired against the host's class —
 * a swap applied after boot would mask a seam that is only honoured at runtime.
 */
abstract class ConfiguredPostTestCase extends TestCase
{
    /**
     * This used to override `defineEnvironment()` (with `parent::`, so it was not the silent
     * decapitation step 3a warns about). It moves to `configBeforeBoot()` anyway: that is the
     * hook the base case exposes for exactly this, and it removes the standing hazard that a
     * later edit drops the `parent::` call and quietly leaves DriverMatrix unconfigured — a
     * "pgsql" leg running sqlite, with no error and no red.
     *
     * Note `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard
     * the base case's two key-type axes and its media wiring — the same decapitation one
     * level down.
     *
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'posts.model' => CustomPost::class,
        ]);
    }
}
