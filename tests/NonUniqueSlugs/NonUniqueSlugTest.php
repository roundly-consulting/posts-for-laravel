<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Posts\Models\Category;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;

it('does not enforce uniqueness when disabled', function (): void {
    Post::factory()->withTitles(['en' => 'Hello World'])->create();
    $second = Post::factory()->withTitles(['en' => 'Hello World'])->create();

    expect($second->getTranslation('slug', 'en'))->toBe('hello-world');
});

it('builds no slug indexes when uniqueness is off at migrate time', function (): void {
    foreach ([
        (string) config('posts.tables.posts'),
        (string) config('posts.tables.categories'),
        (string) config('posts.tables.tags'),
    ] as $table) {
        $names = collect(Schema::getIndexes($table))->pluck('name')->all();

        expect($names)->not->toContain("{$table}_slug_en_slug_unique")
            ->and($names)->not->toContain("{$table}_slug_sk_slug_unique");
    }
});

it('lets categories and tags share a slug when disabled', function (): void {
    Category::factory()->create(['name' => ['en' => 'News']]);
    Tag::factory()->create(['name' => ['en' => 'News']]);

    expect(Category::factory()->create(['name' => ['en' => 'News']])->getTranslation('slug', 'en'))->toBe('news')
        ->and(Tag::factory()->create(['name' => ['en' => 'News']])->getTranslation('slug', 'en'))->toBe('news');
});
