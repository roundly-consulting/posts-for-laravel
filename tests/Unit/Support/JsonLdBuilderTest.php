<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Posts\Models\Category;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\Support\JsonLdBuilder;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;

afterEach(fn () => CarbonImmutable::setTestNow());

it('builds the core BlogPosting keys', function (): void {
    CarbonImmutable::setTestNow('2026-01-01 10:00:00');

    $post = Post::factory()->published()->create([
        'title' => ['en' => 'Headline'],
        'perex' => ['en' => 'Summary.'],
    ]);

    $data = app(JsonLdBuilder::class)->build($post);

    expect($data['@context'])->toBe('https://schema.org')
        ->and($data['@type'])->toBe('BlogPosting')
        ->and($data['headline'])->toBe('Headline')
        ->and($data['description'])->toBe('Summary.')
        ->and($data['datePublished'])->toBe(CarbonImmutable::parse('2026-01-01 10:00:00')->toIso8601String());
});

it('honours the configured schema type', function (): void {
    config()->set('posts.json-ld.type', 'Article');

    expect(Post::factory()->create()->toJsonLd()['@type'])->toBe('Article');
});

it('includes the resolved author name', function (): void {
    $author = AuthorTestModel::create(['name' => 'Jane Doe']);
    $post = Post::factory()->forAuthor($author)->create();

    $data = $post->toJsonLd();

    expect($data['author']['name'])->toBe('Jane Doe');
});

it('omits the author when none is set', function (): void {
    expect(Post::factory()->create()->toJsonLd())->not->toHaveKey('author');
});

it('includes tag keywords and category sections', function (): void {
    $post = Post::factory()->create();
    $post->tags()->attach(Tag::factory()->create(['name' => ['en' => 'php']]));
    $post->categories()->attach(Category::factory()->create(['name' => ['en' => 'Tutorials']]));

    $data = $post->load(['tags', 'categories'])->toJsonLd();

    expect($data['keywords'])->toContain('php')
        ->and($data['articleSection'])->toContain('Tutorials');
});

it('includes the image and canonical entity when available', function (): void {
    $post = Post::factory()->create(['title' => ['en' => 'With Meta']]);
    $post->setSeo(new RoundlyConsulting\Posts\DataTransferObjects\SeoData(
        canonical: 'https://example.test/with-meta',
        ogImage: 'https://img.test/cover.png',
    ));
    $post->save();

    $data = $post->fresh()->toJsonLd();

    expect($data['image'])->toBe('https://img.test/cover.png')
        ->and($data['mainEntityOfPage']['@id'])->toBe('https://example.test/with-meta')
        ->and($data)->toHaveKey('dateModified');
});

it('includes the publisher when configured', function (): void {
    config()->set('posts.json-ld.publisher.name', 'Acme Inc');
    config()->set('posts.json-ld.publisher.logo', 'https://acme.test/logo.png');

    $data = Post::factory()->create()->toJsonLd();

    expect($data['publisher']['name'])->toBe('Acme Inc')
        ->and($data['publisher']['logo']['url'])->toBe('https://acme.test/logo.png');
});
