<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class PostScheduled
{
    use Dispatchable;

    public function __construct(
        public readonly string $postId,
    ) {}
}
