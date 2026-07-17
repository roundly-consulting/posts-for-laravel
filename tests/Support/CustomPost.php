<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests\Support;

use Carbon\CarbonInterface;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

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
    /**
     * Required by `toHonourModelSwap`, and complementary to `$published` below rather than a
     * duplicate of it: `$published` proves the host's own OVERRIDE ran, this proves the ROW
     * was created as this exact class. Asserting the concrete class of a returned object
     * cannot tell a row really created as CustomPost from one created as the packaged Post
     * and re-hydrated (permissions #31) — counting `created` events is the only oracle that
     * can, and without the trait the assertion silently drops that half.
     */
    use CountsCreations;

    public static int $published = 0;

    public function publish(?CarbonInterface $at = null): self
    {
        self::$published++;

        parent::publish($at);

        return $this;
    }
}
