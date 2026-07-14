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
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        config()->set('posts.model', CustomPost::class);
    }
}
