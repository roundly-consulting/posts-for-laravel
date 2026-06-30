<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Enums;

use RoundlyConsulting\Enums\Helpers;

enum PostStatus: string
{
    use Helpers;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
