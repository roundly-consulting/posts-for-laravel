<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Posts\Models\Post;

beforeEach(function (): void {
    Route::middleware(SubstituteBindings::class)->get('/posts/{post:slug}', fn (Post $post) => $post->getKey());
});

it('binds a post by its translated slug in the current locale', function (): void {
    app()->setLocale('en');

    $post = Post::factory()->withTitles(['en' => 'Hello World'])->create();

    $this->get('/posts/hello-world')
        ->assertOk()
        ->assertSee($post->getKey());
});

it('falls back to the fallback locale slug when the current locale has none', function (): void {
    config()->set('posts.locales.fallback', 'en');
    app()->setLocale('sk');

    $post = Post::factory()->withTitles(['en' => 'Fallback Slug'])->create();

    $this->get('/posts/fallback-slug')
        ->assertOk()
        ->assertSee($post->getKey());
});

it('returns 404 for an unknown slug', function (): void {
    $this->get('/posts/missing')->assertNotFound();
});

it('resolves a non-slug field through the default resolver', function (): void {
    $post = Post::factory()->create();

    $resolved = (new Post)->resolveRouteBinding($post->getKey(), 'id');

    expect($resolved?->is($post))->toBeTrue();
});

it('rescues a stale locale from any other locale', function (): void {
    // Neither the current locale nor the fallback holds a slug — only `en` does. Sluggable's
    // `Any` chain still binds it instead of 404ing a link shared from the English site.
    config()->set('posts.locales.fallback', 'de');
    app()->setLocale('sk');

    $post = Post::factory()->withTitles(['en' => 'English Only'])->create();

    $this->get('/posts/english-only')
        ->assertOk()
        ->assertSee($post->getKey());
});

it('uses the current-locale slug as the route key', function (): void {
    app()->setLocale('sk');

    $post = Post::factory()->withTitles(['en' => 'Hello World', 'sk' => 'Ahoj Svet'])->create();

    expect($post->getRouteKeyName())->toBe('slug')
        ->and($post->getRouteKey())->toBe('ahoj-svet');
});

it('binds the implicit {post} parameter by slug so generated URLs round-trip', function (): void {
    Route::middleware(SubstituteBindings::class)
        ->get('/articles/{post}', fn (Post $post) => $post->getKey())
        ->name('articles.show');

    $post = Post::factory()->withTitles(['en' => 'Round Trip'])->create();

    expect(route('articles.show', $post))->toEndWith('/articles/round-trip');

    $this->get(route('articles.show', $post))
        ->assertOk()
        ->assertSee($post->getKey());
});

it('keeps the id as the route key when slug binding is off', function (): void {
    config()->set('posts.slugs.route-binding', false);

    $post = Post::factory()->withTitles(['en' => 'Hello World'])->create();

    expect($post->getRouteKeyName())->toBe('id');
});

it('does not let an id shadow a numeric slug', function (): void {
    $first = Post::factory()->withTitles(['en' => 'First Post'])->create();
    $numeric = Post::factory()->withTitles(['en' => (string) $first->getKey()])->create();

    expect($numeric->getTranslation('slug', 'en'))->toBe((string) $first->getKey());

    $this->get('/posts/'.$first->getKey())
        ->assertOk()
        ->assertSee((string) $numeric->getKey());
});

it('answers a retired slug with a permanent redirect when history is on', function (): void {
    config()->set('posts.slugs.history', true);

    $post = Post::factory()->withTitles(['en' => 'Old Title'])->create();

    $post->setTranslation('slug', 'en', 'new-title');
    $post->save();

    $this->get('/posts/old-title?ref=newsletter')
        ->assertStatus(301)
        ->assertRedirect('/posts/new-title?ref=newsletter');

    $this->get('/posts/new-title')->assertOk();
});

it('404s a retired slug when history is off', function (): void {
    $post = Post::factory()->withTitles(['en' => 'Old Title'])->create();

    $post->setTranslation('slug', 'en', 'new-title');
    $post->save();

    $this->get('/posts/old-title')->assertNotFound();
});
