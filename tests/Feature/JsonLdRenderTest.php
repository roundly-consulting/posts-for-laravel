<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Models\Post;

it('renders the JSON-LD script tag for a post', function (): void {
    $post = Post::factory()->published()->create(['title' => ['en' => 'Structured']]);

    $html = (string) $post->renderJsonLd();

    expect($html)
        ->toContain('<script type="application/ld+json">')
        ->toContain('"@type":"BlogPosting"')
        ->toContain('"headline":"Structured"')
        ->toContain('</script>');
});
