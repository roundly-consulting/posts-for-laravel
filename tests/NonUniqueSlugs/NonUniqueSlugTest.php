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

it('filters by every category and tag that shares the slug when disabled', function (): void {
    // Without uniqueness two categories may both be `news` in the current locale; a slug filter
    // must keep all of them, and still ignore a third that is `news` only in another locale.
    $first = Category::factory()->create(['name' => ['en' => 'News']]);
    $second = Category::factory()->create(['name' => ['en' => 'News']]);
    $slovak = Category::factory()->create(['name' => ['sk' => 'News']]);
    $tagged = Tag::factory()->create(['name' => ['en' => 'News']]);
    $alsoTagged = Tag::factory()->create(['name' => ['en' => 'News']]);

    $posts = collect([$first, $second, $slovak])->map(function (Category $category): Post {
        $post = Post::factory()->create();
        $post->categories()->attach($category);

        return $post;
    });

    $posts[0]->tags()->attach($tagged);
    $posts[1]->tags()->attach($alsoTagged);

    expect(Post::query()->inCategory('news')->orderBy('id')->pluck('id')->all())->toBe([$posts[0]->getKey(), $posts[1]->getKey()])
        ->and(Post::query()->withTag('news')->count())->toBe(2);
});
