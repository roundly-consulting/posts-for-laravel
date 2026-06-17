<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Posts\Actions\CreatePostAction;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostTranslationData;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Enums\PostStatus;
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
