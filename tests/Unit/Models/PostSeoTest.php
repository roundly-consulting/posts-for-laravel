<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Post;

it('falls back meta title and description to the title and perex', function (): void {
    $post = Post::factory()->create([
        'title' => ['en' => 'My Title'],
        'perex' => ['en' => 'My summary.'],
    ]);

    $seo = $post->seo();

    expect($seo->metaTitle)->toBe('My Title')
        ->and($seo->metaDescription)->toBe('My summary.');
});

it('prefers explicit meta fields over the fallbacks', function (): void {
    $post = Post::factory()->create([
        'title' => ['en' => 'My Title'],
        'meta_title' => ['en' => 'SEO Title'],
        'meta_description' => ['en' => 'SEO description.'],
    ]);

    $seo = $post->seo();

    expect($seo->metaTitle)->toBe('SEO Title')
        ->and($seo->metaDescription)->toBe('SEO description.');
});

it('applies configured SEO defaults', function (): void {
    config()->set('posts.seo.default-robots', 'noindex,nofollow');
    config()->set('posts.seo.default-card', 'summary');

    $seo = Post::factory()->create()->seo();

    expect($seo->robots)->toBe('noindex,nofollow')
        ->and($seo->twitterCard)->toBe('summary')
        ->and($seo->ogType)->toBe('article');
});

it('persists the non-translatable SEO bag and translatable meta', function (): void {
    $post = Post::factory()->create();

    $post->setSeo(new SeoData(
        metaTitle: 'Meta',
        metaDescription: 'Desc',
        canonical: 'https://example.test/post',
        ogTitle: 'OG',
        robots: 'index',
    ));
    $post->save();

    $fresh = $post->fresh();

    expect($fresh->getTranslation('meta_title', 'en'))->toBe('Meta')
        ->and($fresh->seo()->canonical)->toBe('https://example.test/post')
        ->and($fresh->seo()->ogTitle)->toBe('OG');
});

it('returns null meta fields when the post has no title or perex', function (): void {
    $post = Post::factory()->create(['title' => ['en' => ''], 'perex' => ['en' => '']]);

    $seo = $post->seo();

    expect($seo->metaTitle)->toBeNull()
        ->and($seo->metaDescription)->toBeNull();
});

it('round-trips the SEO bag through the DTO', function (): void {
    $data = new SeoData(canonical: 'https://example.test', ogImage: 'https://img.test/a.png');

    $bag = $data->toBag();
    $rebuilt = SeoData::fromBag($bag);

    expect($rebuilt->canonical)->toBe('https://example.test')
        ->and($rebuilt->ogImage)->toBe('https://img.test/a.png');
});
