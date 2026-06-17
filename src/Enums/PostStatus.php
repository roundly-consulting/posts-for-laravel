<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return (string) trans("posts::statuses.{$this->value}");
    }

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
