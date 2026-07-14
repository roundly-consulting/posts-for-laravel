<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests\Support;

use Carbon\CarbonInterface;
use RoundlyConsulting\Posts\Models\Post;

/**
 * A host's own post model — exactly what `config('posts.model')` invites. It is
 * only writable at all because `Post` is not `final`.
 *
 * The counter makes the swap OBSERVABLE: a call site that reaches for the packaged
 * `Post` still reads and writes the same table, so a bypassed seam looks identical
 * unless the host model's own behaviour is what has to run.
 */
final class CustomPost extends Post
{
    public static int $published = 0;

    public function publish(?CarbonInterface $at = null): self
    {
        self::$published++;

        parent::publish($at);

        return $this;
    }
}
