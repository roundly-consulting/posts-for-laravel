<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Actions\SyncPostTagsAction;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;

it('finds or creates tags by name and syncs them', function (): void {
    $existing = Tag::factory()->create(['name' => ['en' => 'php']]);
    $model = Tag::factory()->create(['name' => ['en' => 'eloquent']]);
    $post = Post::factory()->create();

    $result = app(SyncPostTagsAction::class)->execute($post, ['php', 'laravel', $model]);

    expect($result)->toBe($post)
        ->and(Tag::query()->count())->toBe(3)
        ->and($post->tags()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$existing->id, $model->id, Tag::query()->where('name->en', 'laravel')->value('id')])->sort()->values()->all());
});

it('detaches the tags it is not given', function (): void {
    $post = Post::factory()->create();

    app(SyncPostTagsAction::class)->execute($post, ['php', 'laravel']);
    app(SyncPostTagsAction::class)->execute($post, ['php']);

    expect($post->tags()->count())->toBe(1)
        ->and(Tag::query()->count())->toBe(2);
});

it('matches tag names in the current locale', function (): void {
    app()->setLocale('sk');
    $post = Post::factory()->create();

    app(SyncPostTagsAction::class)->execute($post, ['novinky']);

    expect(Tag::query()->sole()->getTranslations('name'))->toBe(['sk' => 'novinky']);
});
