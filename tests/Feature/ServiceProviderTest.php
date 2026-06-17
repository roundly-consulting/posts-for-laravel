<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Posts\Commands\PublishScheduledPostsCommand;

it('registers the package config', function (): void {
    expect(config('posts.model'))->not->toBeNull();
});

it('creates every package table', function (): void {
    expect(Schema::hasTable(config('posts.tables.posts')))->toBeTrue()
        ->and(Schema::hasTable(config('posts.tables.categories')))->toBeTrue()
        ->and(Schema::hasTable(config('posts.tables.category_post')))->toBeTrue()
        ->and(Schema::hasTable(config('posts.tables.tags')))->toBeTrue()
        ->and(Schema::hasTable(config('posts.tables.tag_post')))->toBeTrue();
});

it('registers the scheduled-publish command', function (): void {
    expect(array_keys($this->app[Illuminate\Contracts\Console\Kernel::class]->all()))
        ->toContain('posts:publish-scheduled');

    expect(new PublishScheduledPostsCommand)->toBeInstanceOf(PublishScheduledPostsCommand::class);
});

it('renders package views', function (): void {
    expect(view()->exists('posts::meta'))->toBeTrue()
        ->and(view()->exists('posts::json-ld'))->toBeTrue();
});

it('registers status translations', function (): void {
    expect(trans('posts::statuses.draft'))->toBe('Draft');
});
