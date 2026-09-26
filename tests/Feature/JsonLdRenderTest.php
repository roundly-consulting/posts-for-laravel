<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Category;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;

it('renders the JSON-LD script tag for a post', function (): void {
    $post = Post::factory()->published()->create(['title' => ['en' => 'Structured']]);

    $html = (string) $post->renderJsonLd();

    expect($html)
        ->toContain('<script type="application/ld+json">')
        ->toContain('"@type":"BlogPosting"')
        ->toContain('"headline":"Structured"')
        ->toContain('</script>');
});

it('never lets a stored value break out of the JSON-LD script element', function (): void {
    // Every user-controlled field that reaches the JSON-LD: title, perex (description), author
    // name, tag (keywords) and category (articleSection) names.
    $payload = '</script><script>alert(1)</script><!-- & \' "';
    $author = AuthorTestModel::create(['name' => $payload]);

    $post = Post::factory()->published()->forAuthor($author)->create([
        'title' => ['en' => $payload],
        'perex' => ['en' => $payload],
    ]);
    $post->tags()->attach(Tag::factory()->create(['name' => ['en' => $payload]]));
    $post->categories()->attach(Category::factory()->create(['name' => ['en' => $payload]]));

    $html = (string) $post->fresh()?->renderJsonLd();
    $json = Str::between($html, '<script type="application/ld+json">', '</script>');

    expect(substr_count($html, '</script>'))->toBe(1)
        ->and(substr_count(strtolower($html), '<script'))->toBe(1)
        ->and($html)->not->toContain('<!--')
        ->and(json_decode($json, true, 512, JSON_THROW_ON_ERROR))->toMatchArray([
            'headline' => $payload,
            'description' => $payload,
            'author' => ['@type' => 'Person', 'name' => $payload],
            'keywords' => [$payload],
            'articleSection' => [$payload],
        ]);
});

it('keeps URLs and non-ASCII text readable in the JSON-LD', function (): void {
    $post = Post::factory()->published()->create(['title' => ['en' => 'Čaj a káva']]);
    $post->setSeo(new SeoData(canonical: 'https://example.test/posts/caj'))->save();

    expect((string) $post->fresh()?->renderJsonLd())
        ->toContain('"headline":"Čaj a káva"')
        ->toContain('"@id":"https://example.test/posts/caj"');
});
