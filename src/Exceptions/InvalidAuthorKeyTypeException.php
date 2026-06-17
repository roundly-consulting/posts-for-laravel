<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Exceptions;

final class InvalidAuthorKeyTypeException extends PostsException
{
    public static function for(string $value): self
    {
        return new self(
            "Invalid posts author key-type [{$value}]. Allowed values are: bigint, uuid.",
        );
    }
}
