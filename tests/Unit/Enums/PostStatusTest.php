<?php

declare(strict_types=1);

use RoundlyConsulting\Enums\DataTransferObjects\EnumOption;
use RoundlyConsulting\Posts\Enums\PostStatus;

it('exposes a readable label for each status via the enums trait', function (): void {
    expect(PostStatus::Draft->label())->toBe('Draft')
        ->and(PostStatus::Scheduled->label())->toBe('Scheduled')
        ->and(PostStatus::Published->label())->toBe('Published')
        ->and(PostStatus::Archived->label())->toBe('Archived')
        ->and(PostStatus::Published->readable())->toBe('Published');
});

it('lists every status label and backed value', function (): void {
    expect(PostStatus::labels()->all())->toBe(['Draft', 'Scheduled', 'Published', 'Archived'])
        ->and(PostStatus::values()->all())->toBe(['draft', 'scheduled', 'published', 'archived']);
});

it('builds select options as EnumOption DTOs', function (): void {
    $options = PostStatus::options();

    expect($options)->toHaveCount(4)
        ->and($options->first())->toBeInstanceOf(EnumOption::class)
        ->and($options->first()->toArray())->toBe([
            'value' => 'draft',
            'label' => 'Draft',
            'name' => 'Draft',
        ]);

    expect(PostStatus::toOptions()->all())->toBe([
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
        'archived' => 'Archived',
    ]);
});

it('builds a validation rule from the backed values', function (): void {
    expect(PostStatus::validationRule())->toBe('in:draft,scheduled,published,archived');
});

it('resolves cases by name and value through the trait', function (): void {
    expect(PostStatus::fromName('Published'))->toBe(PostStatus::Published)
        ->and(PostStatus::tryFromName('Missing'))->toBeNull()
        ->and(PostStatus::hasValue('archived'))->toBeTrue()
        ->and(PostStatus::hasValue('void'))->toBeFalse();
});

it('reports only the published status as public', function (): void {
    expect(PostStatus::Published->isPublic())->toBeTrue()
        ->and(PostStatus::Draft->isPublic())->toBeFalse()
        ->and(PostStatus::Scheduled->isPublic())->toBeFalse()
        ->and(PostStatus::Archived->isPublic())->toBeFalse();
});
