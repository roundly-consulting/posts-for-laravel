<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Exceptions\InvalidCategoryParentException;
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

it('refuses to make a category its own parent', function (): void {
    $category = Category::factory()->create();

    expect(fn () => $category->update(['parent_id' => $category->getKey()]))
        ->toThrow(InvalidCategoryParentException::class);

    expect($category->fresh()?->parent_id)->toBeNull();
});

it('refuses a parent that would close a cycle', function (): void {
    $a = Category::factory()->create();
    $b = Category::factory()->childOf($a)->create();
    $c = Category::factory()->childOf($b)->create();

    expect(fn () => $a->update(['parent_id' => $c->getKey()]))
        ->toThrow(InvalidCategoryParentException::class)
        ->and(fn () => $a->update(['parent_id' => $b->getKey()]))
        ->toThrow(InvalidCategoryParentException::class);

    expect($a->fresh()?->parent_id)->toBeNull();
});

it('refuses a cycle through a trashed category', function (): void {
    $a = Category::factory()->create();
    $b = Category::factory()->childOf($a)->create();
    $c = Category::factory()->childOf($b)->create();
    $b->delete();

    $a->update(['parent_id' => $c->getKey()]);
})->throws(InvalidCategoryParentException::class);

it('still re-parents a category without a cycle', function (): void {
    $a = Category::factory()->create();
    $b = Category::factory()->create();
    $c = Category::factory()->childOf($a)->create();

    $c->update(['parent_id' => $b->getKey()]);
    $c->update(['parent_id' => null]);
    $a->update(['parent_id' => $b->getKey()]);

    expect($a->fresh()?->parent_id)->toBe($b->getKey())
        ->and($c->fresh()?->parent_id)->toBeNull();
});

it('terminates ancestors and descendants on a cycle written past the model', function (): void {
    $a = Category::factory()->create();
    $b = Category::factory()->childOf($a)->create();
    $c = Category::factory()->childOf($b)->create();

    Category::query()->toBase()->where('id', $a->getKey())->update(['parent_id' => $c->getKey()]);
    $a = $a->fresh();
    $self = Category::factory()->create();
    Category::query()->toBase()->where('id', $self->getKey())->update(['parent_id' => $self->getKey()]);
    $self = $self->fresh();

    expect($a?->ancestors()->pluck('id')->all())->toBe([$c->getKey(), $b->getKey()])
        ->and($a?->descendants()->pluck('id')->all())->toBe([$b->getKey(), $c->getKey()])
        ->and($self?->ancestors()->all())->toBe([])
        ->and($self?->descendants()->all())->toBe([]);
});
