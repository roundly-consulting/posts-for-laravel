<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Exceptions;

use RoundlyConsulting\Posts\Enums\PostStatus;

final class InvalidPostStatusTransitionException extends PostsException
{
    public static function between(PostStatus $from, PostStatus $to): self
    {
        return new self(
            "Cannot transition a post from [{$from->value}] to [{$to->value}].",
        );
    }

    public static function scheduledWithoutDate(): self
    {
        return new self('A scheduled post needs a publish date.');
    }
}
