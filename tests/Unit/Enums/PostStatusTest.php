<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Enums\PostStatus;

it('exposes a translated label for each status', function (): void {
    expect(PostStatus::Draft->label())->toBe('Draft')
        ->and(PostStatus::Scheduled->label())->toBe('Scheduled')
        ->and(PostStatus::Published->label())->toBe('Published')
        ->and(PostStatus::Archived->label())->toBe('Archived');
});

it('reports only the published status as public', function (): void {
    expect(PostStatus::Published->isPublic())->toBeTrue()
        ->and(PostStatus::Draft->isPublic())->toBeFalse()
        ->and(PostStatus::Scheduled->isPublic())->toBeFalse()
        ->and(PostStatus::Archived->isPublic())->toBeFalse();
});
