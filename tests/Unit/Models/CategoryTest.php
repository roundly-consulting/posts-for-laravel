<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Models\Category;
use RoundlyConsulting\Posts\Models\Post;

it('auto-generates a translated slug from the name', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'Laravel Tips', 'sk' => 'Laravel Tipy']]);

    expect($category->getTranslation('slug', 'en'))->toBe('laravel-tips')
        ->and($category->getTranslation('slug', 'sk'))->toBe('laravel-tipy');
});

it('attaches and syncs many posts', function (): void {
    $category = Category::factory()->create();
    $first = Post::factory()->create();
    $second = Post::factory()->create();

    $category->posts()->attach([$first->getKey(), $second->getKey()]);

    expect($category->posts()->count())->toBe(2);
});

it('resolves parent and children', function (): void {
    $parent = Category::factory()->create(['name' => ['en' => 'Guides']]);
    $child = Category::factory()->childOf($parent)->create(['name' => ['en' => 'Laravel']]);

    expect($child->parent->is($parent))->toBeTrue()
        ->and($parent->children->first()->is($child))->toBeTrue();
});

it('walks ancestors and descendants', function (): void {
    $root = Category::factory()->create(['name' => ['en' => 'Root']]);
    $mid = Category::factory()->childOf($root)->create(['name' => ['en' => 'Mid']]);
    $leaf = Category::factory()->childOf($mid)->create(['name' => ['en' => 'Leaf']]);

    expect($leaf->ancestors()->pluck('id')->all())->toBe([$mid->id, $root->id])
        ->and($root->descendants()->pluck('id')->all())->toBe([$mid->id, $leaf->id]);
});

it('soft deletes categories', function (): void {
    $category = Category::factory()->create();
    $category->delete();

    expect(Category::query()->count())->toBe(0)
        ->and(Category::withTrashed()->count())->toBe(1);
});

it('uses the configured table name', function (): void {
    expect((new Category)->getTable())->toBe(config('posts.tables.categories'));
});
