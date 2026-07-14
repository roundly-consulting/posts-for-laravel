<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Post;

it('renders the SEO meta tags for a post', function (): void {
    $post = Post::factory()->create([
        'title' => ['en' => 'My Article'],
        'perex' => ['en' => 'A short summary.'],
    ]);

    $post->setSeo($post->seo());
    $post->save();

    $html = (string) $post->fresh()->renderMetaTags();

    expect($html)
        ->toContain('<title>My Article</title>')
        ->toContain('name="description" content="A short summary."')
        ->toContain('property="og:title" content="My Article"')
        ->toContain('name="twitter:card"')
        ->toContain('name="robots"');
});

it('renders a canonical link when set', function (): void {
    $post = Post::factory()->create(['title' => ['en' => 'Canonical']]);
    $post->seo = ['canonical' => 'https://example.test/canonical'];
    $post->save();

    expect((string) $post->renderMetaTags())
        ->toContain('rel="canonical" href="https://example.test/canonical"');
});

it('renders the configured site name as og:site_name', function (): void {
    // `posts.seo.site-name` shipped since day one and NOTHING read it: no og:site_name
    // tag existed at all, so a host that set POSTS_SITE_NAME got nothing.
    config()->set('posts.seo.site-name', 'Acme Journal');

    $post = Post::factory()->withTitles(['en' => 'Hello'])->create();

    expect((string) $post->renderMetaTags())
        ->toContain('<meta property="og:site_name" content="Acme Journal">');
});

it('lets a post override the site name', function (): void {
    config()->set('posts.seo.site-name', 'Acme Journal');

    $post = Post::factory()->create();
    $post->setSeo(new SeoData(ogSiteName: 'Acme Labs'))->save();

    expect((string) $post->fresh()->renderMetaTags())
        ->toContain('content="Acme Labs"')
        ->not->toContain('Acme Journal');
});

it('renders no og:site_name when none is configured', function (): void {
    $post = Post::factory()->create();

    expect((string) $post->renderMetaTags())->not->toContain('og:site_name');
});
