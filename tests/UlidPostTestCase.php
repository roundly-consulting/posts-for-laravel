<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests;

/**
 * Posts' own tables keyed by ulid — the INBOUND axis, independent of the author key.
 *
 * @see TestCase::postsKeyType()
 */
abstract class UlidPostTestCase extends TestCase
{
    protected function postsKeyType(): string
    {
        return 'ulid';
    }
}
