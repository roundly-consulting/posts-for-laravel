<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Tests\Support\PublishedOnlyPost;
use RoundlyConsulting\Sluggable\Exceptions\SlugLockedException;

it('auto-generates a slug per locale from the title', function (): void {
    $post = Post::factory()->withTitles(['en' => 'Hello World', 'sk' => 'Ahoj Svet'])->create();

    expect($post->getTranslation('slug', 'en'))->toBe('hello-world')
        ->and($post->getTranslation('slug', 'sk'))->toBe('ahoj-svet');
});

it('suffixes colliding slugs to keep them unique', function (): void {
    Post::factory()->withTitles(['en' => 'Hello World'])->create();
    $second = Post::factory()->withTitles(['en' => 'Hello World'])->create();
    $third = Post::factory()->withTitles(['en' => 'Hello World'])->create();

    expect($second->getTranslation('slug', 'en'))->toBe('hello-world-2')
        ->and($third->getTranslation('slug', 'en'))->toBe('hello-world-3');
});

it('respects a manually provided slug', function (): void {
    $post = new Post;
    $post->setTranslation('title', 'en', 'Hello World');
    $post->setTranslation('slug', 'en', 'custom-slug');
    $post->save();

    expect($post->getTranslation('slug', 'en'))->toBe('custom-slug');
});

it('skips slug generation for empty titles', function (): void {
    $post = Post::factory()->create(['title' => ['en' => '']]);

    expect($post->getTranslation('slug', 'en', false))->toBeIn(['', null]);
});

it('skips slug generation when the title slugifies to an empty string', function (): void {
    $post = Post::factory()->create(['title' => ['en' => '###']]);

    expect($post->getTranslation('slug', 'en', false))->toBeIn(['', null]);
});

it('skips empty locales while slugging the populated ones', function (): void {
    $post = Post::factory()->create(['title' => ['en' => 'Hello', 'sk' => '']]);

    expect($post->getTranslation('slug', 'en'))->toBe('hello')
        ->and($post->getTranslation('slug', 'sk', false))->toBeIn(['', null]);
});

it('counts a trashed post as a collision', function (): void {
    Post::factory()->withTitles(['en' => 'Hello World'])->create()->delete();

    $post = Post::factory()->withTitles(['en' => 'Hello World'])->create();

    expect($post->getTranslation('slug', 'en'))->toBe('hello-world-2');
});

it('counts a post hidden by a global scope as a collision', function (): void {
    Post::factory()->withTitles(['en' => 'Hello World'])->create(['status' => PostStatus::Draft]);

    // The host model's own scope cannot see the draft that already holds the slug.
    expect(PublishedOnlyPost::query()->count())->toBe(0);

    $post = new PublishedOnlyPost;
    $post->status = PostStatus::Draft;
    $post->setTranslation('title', 'en', 'Hello World');
    $post->save();

    expect($post->getTranslation('slug', 'en'))->toBe('hello-world-2');
});

it('normalises a manually provided slug', function (): void {
    $post = new Post;
    $post->setTranslation('title', 'en', 'Hello World');
    $post->setTranslation('slug', 'en', 'Custom Slug');
    $post->save();

    expect($post->getTranslation('slug', 'en'))->toBe('custom-slug');
});

it('makes a duplicate manual slug unique', function (): void {
    Post::factory()->withTitles(['en' => 'Taken'])->create();

    $post = new Post;
    $post->setTranslation('title', 'en', 'Something Else');
    $post->setTranslation('slug', 'en', 'taken');
    $post->save();

    expect($post->getTranslation('slug', 'en'))->toBe('taken-2');
});

it('fills a missing locale on a dirty save and keeps the existing one', function (): void {
    $post = Post::factory()->withTitles(['en' => 'Hello World'])->create();

    $post->setTranslation('title', 'en', 'Renamed');
    $post->setTranslation('title', 'sk', 'Ahoj Svet');
    $post->save();

    expect($post->getTranslation('slug', 'en'))->toBe('hello-world')
        ->and($post->getTranslation('slug', 'sk'))->toBe('ahoj-svet');
});

it('reads the current slug along the locale chain', function (): void {
    config()->set('posts.locales.fallback', 'en');
    $post = Post::factory()->withTitles(['en' => 'Hello World', 'sk' => 'Ahoj Svet'])->create();

    app()->setLocale('sk');
    expect($post->currentSlug())->toBe('ahoj-svet')
        ->and($post->slugFor('en'))->toBe('hello-world');

    app()->setLocale('de');
    expect($post->currentSlug())->toBe('hello-world');
});

it('does not lock published slugs by default', function (): void {
    $post = Post::factory()->withTitles(['en' => 'Hello World'])->create();
    $post->publish();

    $post->setTranslation('slug', 'en', 'moved');
    $post->save();

    expect($post->refresh()->getTranslation('slug', 'en'))->toBe('moved');
});

it('locks the slugs of a published post when configured', function (): void {
    config()->set('posts.slugs.lock-when-published', true);

    $post = Post::factory()->withTitles(['en' => 'Hello World'])->create();
    $post->publish();

    // A new locale is an automatic change — the lock blocks it.
    $post->setTranslation('title', 'sk', 'Ahoj Svet');
    $post->save();

    expect($post->refresh()->getTranslation('slug', 'sk', false))->toBeIn(['', null]);

    // A manual change is refused outright.
    $post->setTranslation('slug', 'en', 'moved');

    expect(fn () => $post->save())->toThrow(SlugLockedException::class);
});

it('leaves a draft editable while the lock is on', function (): void {
    config()->set('posts.slugs.lock-when-published', true);

    $post = Post::factory()->withTitles(['en' => 'Hello World'])->create();

    $post->setTranslation('slug', 'en', 'moved');
    $post->save();

    expect($post->refresh()->getTranslation('slug', 'en'))->toBe('moved');
});

it('exposes validation rules that reject a taken slug', function (): void {
    $taken = Post::factory()->withTitles(['en' => 'Hello World'])->create();

    $rules = Post::slugRules();

    expect(Validator::make(['slug' => ['en' => 'hello-world']], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['slug' => ['en' => 'fresh-slug']], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['slug' => null], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['slug' => ['en' => 'hello-world']], Post::slugRules($taken))->passes())->toBeTrue();
});
