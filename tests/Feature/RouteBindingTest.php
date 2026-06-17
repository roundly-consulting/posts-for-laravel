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
