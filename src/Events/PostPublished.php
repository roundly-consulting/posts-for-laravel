<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class PostPublished
{
    use Dispatchable;

    public function __construct(
        public readonly int|string $postId,
    ) {}
}
