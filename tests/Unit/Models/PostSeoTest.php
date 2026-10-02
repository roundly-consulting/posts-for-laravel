<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Category;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;

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

it('uses the same locale fallback as the title for meta, tags and json-ld', function (): void {
    $post = Post::factory()->create([
        'title' => ['en' => 'Only english'],
        'perex' => ['en' => 'English summary.'],
    ]);
    $post->tags()->attach(Tag::factory()->create(['name' => ['en' => 'php']]));
    $post->categories()->attach(Category::factory()->create(['name' => ['en' => 'Guides']]));

    app()->setLocale('sk');

    $seo = $post->fresh()?->seo();
    $jsonLd = $post->fresh()?->toJsonLd();

    expect($post->title)->toBe('Only english')
        ->and($seo?->metaTitle)->toBe('Only english')
        ->and($seo?->metaDescription)->toBe('English summary.')
        ->and($seo?->ogTitle)->toBe('Only english')
        ->and((string) $post->renderMetaTags())->toContain('<title>Only english</title>')
        ->and($jsonLd['headline'] ?? null)->toBe('Only english')
        ->and($jsonLd['description'] ?? null)->toBe('English summary.')
        ->and($jsonLd['keywords'] ?? null)->toBe(['php'])
        ->and($jsonLd['articleSection'] ?? null)->toBe(['Guides']);
});

it('prefers the current locale title over a fallback-locale meta title', function (): void {
    $post = Post::factory()->create([
        'title' => ['en' => 'English', 'sk' => 'Slovensky'],
        'meta_title' => ['en' => 'English SEO'],
        'perex' => ['en' => 'English summary.', 'sk' => 'Slovenske zhrnutie.'],
        'meta_description' => ['en' => 'English SEO summary.'],
    ]);

    app()->setLocale('sk');

    expect($post->seo()->metaTitle)->toBe('Slovensky')
        ->and($post->seo()->metaDescription)->toBe('Slovenske zhrnutie.')
        ->and($post->toJsonLd()['headline'])->toBe('Slovensky');
});

it('falls back to the fallback-locale meta title before its plain title', function (): void {
    $post = Post::factory()->create([
        'title' => ['en' => 'English'],
        'meta_title' => ['en' => 'English SEO'],
    ]);

    app()->setLocale('sk');

    expect($post->seo()->metaTitle)->toBe('English SEO');
});
