<?php

declare(strict_types=1);

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
