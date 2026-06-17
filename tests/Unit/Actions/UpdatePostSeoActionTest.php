<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Actions\UpdatePostSeoAction;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Post;

it('updates a post SEO bag through the action', function (): void {
    $post = Post::factory()->create();

    app(UpdatePostSeoAction::class)->execute($post, new SeoData(
        metaTitle: 'SEO Title',
        canonical: 'https://example.test/seo',
        robots: 'noindex',
    ));

    $fresh = $post->fresh();

    expect($fresh->getTranslation('meta_title', 'en'))->toBe('SEO Title')
        ->and($fresh->seo()->canonical)->toBe('https://example.test/seo')
        ->and($fresh->seo()->robots)->toBe('noindex');
});
