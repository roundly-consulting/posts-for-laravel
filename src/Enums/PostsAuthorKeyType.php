<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Enums;

use Illuminate\Database\Schema\Blueprint;
use RoundlyConsulting\Enums\Helpers;
use RoundlyConsulting\Posts\Exceptions\InvalidAuthorKeyTypeException;

enum PostsAuthorKeyType: string
{
    use Helpers;

    case Bigint = 'bigint';
    case Uuid = 'uuid';

    public static function fromConfig(): self
    {
        $value = config('posts.author.key-type', self::Bigint->value);

        if ($value === null) {
            return self::Bigint;
        }

        if (! is_string($value)) {
            throw InvalidAuthorKeyTypeException::for((string) json_encode($value));
        }

        return self::tryFrom($value)
            ?? throw InvalidAuthorKeyTypeException::for($value);
    }

    public function columnDefinition(Blueprint $table, string $column, bool $nullable): void
    {
        $definition = match ($this) {
            self::Uuid => $table->uuid($column),
            self::Bigint => $table->unsignedBigInteger($column),
        };

        if ($nullable) {
            $definition->nullable();
        }
    }
}
