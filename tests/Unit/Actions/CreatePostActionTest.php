<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Posts\Actions\CreatePostAction;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostTranslationData;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Events\PostScheduled;
use RoundlyConsulting\Posts\Exceptions\InvalidPostStatusTransitionException;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;

it('creates a multilingual post from a DTO', function (): void {
    $author = AuthorTestModel::create(['name' => 'John']);

    $post = app(CreatePostAction::class)->execute(new CreatePostData(
        translations: [
            new CreatePostTranslationData(locale: 'en', title: 'Hello', content: '<p>Hi</p>'),
            new CreatePostTranslationData(locale: 'sk', title: 'Ahoj', content: '<p>Cau</p>'),
        ],
        status: PostStatus::Published,
        publishedAt: CarbonImmutable::parse('2026-02-02 09:00:00'),
        authorType: $author->getMorphClass(),
        authorId: $author->getKey(),
        seo: new SeoData(canonical: 'https://example.test/hello'),
    ));

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->status)->toBe(PostStatus::Published)
        ->and($post->getTranslation('title', 'en'))->toBe('Hello')
        ->and($post->getTranslation('title', 'sk'))->toBe('Ahoj')
        ->and($post->getTranslation('slug', 'en'))->toBe('hello')
        ->and($post->author->is($author))->toBeTrue()
        ->and($post->seo()->canonical)->toBe('https://example.test/hello');
});

it('creates a draft post without an author', function (): void {
    $post = app(CreatePostAction::class)->execute(new CreatePostData(
        translations: [new CreatePostTranslationData(locale: 'en', title: 'Draft')],
    ));

    expect($post->status)->toBe(PostStatus::Draft)
        ->and($post->author)->toBeNull()
        ->and($post->published_at)->toBeNull();
});

// Regression: a post created Published with no date stored published_at = null, so it never
// matched `published()` (published_at <= now) — the README's own example created invisible posts.
it('dates a post created as published now when no date is given', function (): void {
    CarbonImmutable::setTestNow('2026-04-04 10:00:00');

    $post = app(CreatePostAction::class)->execute(new CreatePostData(
        translations: [new CreatePostTranslationData(locale: 'en', title: 'Live')],
        status: PostStatus::Published,
    ));

    expect($post->published_at?->toDateTimeString())->toBe('2026-04-04 10:00:00')
        ->and(Post::query()->published()->whereKey($post->getKey())->exists())->toBeTrue();

    CarbonImmutable::setTestNow();
});

// Regression: the SEO DTO's meta title/description were dropped on create (only the bag was kept).
it('keeps the seo meta title and description on create', function (): void {
    $post = app(CreatePostAction::class)->execute(new CreatePostData(
        translations: [new CreatePostTranslationData(locale: 'en', title: 'Hello')],
        seo: new SeoData(metaTitle: 'SEO title', metaDescription: 'SEO description', robots: 'noindex'),
    ));

    $fresh = $post->fresh();

    expect($fresh?->getTranslation('meta_title', 'en'))->toBe('SEO title')
        ->and($fresh?->getTranslation('meta_description', 'en'))->toBe('SEO description')
        ->and($fresh?->seo()->robots)->toBe('noindex');
});

it('lets a per-locale meta title win over the seo dto', function (): void {
    $post = app(CreatePostAction::class)->execute(new CreatePostData(
        translations: [new CreatePostTranslationData(locale: 'en', title: 'Hello', metaTitle: 'Locale title')],
        seo: new SeoData(metaTitle: 'SEO title'),
    ));

    expect($post->getTranslation('meta_title', 'en'))->toBe('Locale title');
});

// Regression: a post created already published fired no PostPublished, so listeners (media
// warming, notifications) never saw it go live.
it('dispatches the lifecycle event of the status it creates', function (): void {
    Event::fake([PostPublished::class, PostScheduled::class]);

    app(CreatePostAction::class)->execute(new CreatePostData(
        translations: [new CreatePostTranslationData(locale: 'en', title: 'Live')],
        status: PostStatus::Published,
    ));

    app(CreatePostAction::class)->execute(new CreatePostData(
        translations: [new CreatePostTranslationData(locale: 'en', title: 'Later')],
        status: PostStatus::Scheduled,
        publishedAt: CarbonImmutable::parse('2031-01-01 00:00:00'),
    ));

    app(CreatePostAction::class)->execute(new CreatePostData(
        translations: [new CreatePostTranslationData(locale: 'en', title: 'Draft')],
    ));

    Event::assertDispatchedTimes(PostPublished::class, 1);
    Event::assertDispatchedTimes(PostScheduled::class, 1);
});

// Regression: a scheduled post with no date was stored and then never published.
it('refuses a scheduled post without a publish date', function (): void {
    app(CreatePostAction::class)->execute(new CreatePostData(
        translations: [new CreatePostTranslationData(locale: 'en', title: 'Someday')],
        status: PostStatus::Scheduled,
    ));
})->throws(InvalidPostStatusTransitionException::class, 'A scheduled post needs a publish date.');
