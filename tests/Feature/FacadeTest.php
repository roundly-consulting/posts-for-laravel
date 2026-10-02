<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostTranslationData;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostArchived;
use RoundlyConsulting\Posts\Events\PostDrafted;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Events\PostScheduled;
use RoundlyConsulting\Posts\Exceptions\InvalidPostStatusTransitionException;
use RoundlyConsulting\Posts\Facades\Posts;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\PostsManager;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;

afterEach(fn () => CarbonImmutable::setTestNow());

it('documents its root, is fakeable and reaches every action', function (): void {
    expect(Posts::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});

it('creates a post from a dto through the facade', function (): void {
    $post = Posts::create(new CreatePostData(
        translations: [new CreatePostTranslationData(locale: 'en', title: 'Hello', content: '<p>Hi</p>')],
    ));

    expect($post->exists)->toBeTrue()
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->getTranslation('slug', 'en'))->toBe('hello');
});

it('writes a draft with the builder', function (): void {
    Event::fake([PostPublished::class, PostScheduled::class]);

    $author = AuthorTestModel::create(['name' => 'Ada']);

    $post = Posts::draft()
        ->title('en', 'Hello world')
        ->title('sk', 'Ahoj svet')
        ->slug('sk', 'Vlastny Slug')
        ->perex('en', 'Short')
        ->content('en', '<p>Body</p>')
        ->metaTitle('en', 'Meta title')
        ->metaDescription('en', 'Meta description')
        ->by($author)
        ->tags(['php', 'laravel'])
        ->seo(new SeoData(canonical: 'https://example.test/hello'))
        ->save();

    $fresh = $post->fresh();

    expect($fresh?->status)->toBe(PostStatus::Draft)
        ->and($fresh?->published_at)->toBeNull()
        ->and($fresh?->getTranslations('title'))->toBe(['en' => 'Hello world', 'sk' => 'Ahoj svet'])
        ->and($fresh?->getTranslation('slug', 'sk'))->toBe('vlastny-slug')
        ->and($fresh?->getTranslation('perex', 'en'))->toBe('Short')
        ->and($fresh?->getTranslation('content', 'en'))->toBe('<p>Body</p>')
        ->and($fresh?->getTranslation('meta_title', 'en'))->toBe('Meta title')
        ->and($fresh?->getTranslation('meta_description', 'en'))->toBe('Meta description')
        ->and($fresh?->author?->is($author))->toBeTrue()
        ->and($fresh?->tags()->count())->toBe(2)
        ->and($fresh?->seo()->canonical)->toBe('https://example.test/hello');

    Event::assertNotDispatched(PostPublished::class);
    Event::assertNotDispatched(PostScheduled::class);
});

it('publishes straight from the builder', function (): void {
    Event::fake([PostPublished::class]);
    CarbonImmutable::setTestNow('2026-05-05 09:00:00');

    $now = Posts::draft()->title('en', 'Now')->publish();
    $dated = Posts::draft()->title('en', 'Dated')->publish(CarbonImmutable::parse('2026-05-01 08:00:00'));

    expect($now->fresh()?->status)->toBe(PostStatus::Published)
        ->and($now->fresh()?->published_at?->toDateTimeString())->toBe('2026-05-05 09:00:00')
        ->and($dated->fresh()?->published_at?->toDateTimeString())->toBe('2026-05-01 08:00:00')
        ->and(Posts::published()->count())->toBe(2);

    Event::assertDispatchedTimes(PostPublished::class, 2);
});

it('schedules straight from the builder', function (): void {
    Event::fake([PostScheduled::class]);

    $post = Posts::draft()->title('en', 'Later')->schedule(CarbonImmutable::parse('2031-01-01 00:00:00'));

    expect($post->fresh()?->status)->toBe(PostStatus::Scheduled)
        ->and($post->fresh()?->published_at?->toDateTimeString())->toBe('2031-01-01 00:00:00');

    Event::assertDispatched(PostScheduled::class);
});

it('hands out the dto the builder would create', function (): void {
    $author = AuthorTestModel::create(['name' => 'Ada']);
    $seo = new SeoData(robots: 'noindex');

    $data = Posts::draft()->title('en', 'Hello')->content('sk', '<p>Ahoj</p>')->by($author)->seo($seo)->data();

    expect($data->status)->toBe(PostStatus::Draft)
        ->and($data->authorType)->toBe($author->getMorphClass())
        ->and($data->authorId)->toBe($author->getKey())
        ->and($data->seo)->toBe($seo)
        ->and($data->translations)->toHaveCount(2)
        ->and($data->translations[0]->locale)->toBe('en')
        ->and($data->translations[0]->title)->toBe('Hello')
        ->and($data->translations[0]->content)->toBeNull()
        ->and($data->translations[1]->locale)->toBe('sk')
        ->and($data->translations[1]->content)->toBe('<p>Ahoj</p>');
});

it('leaves the author empty for an unsaved author model', function (): void {
    $data = Posts::draft()->title('en', 'Hello')->by(new AuthorTestModel)->data();

    expect($data->authorId)->toBeNull();
});

it('moves a post through its lifecycle through the facade', function (): void {
    Event::fake([PostPublished::class, PostScheduled::class, PostArchived::class, PostDrafted::class]);
    CarbonImmutable::setTestNow('2026-06-06 06:00:00');

    $post = Post::factory()->draft()->create();

    expect(Posts::schedule($post, CarbonImmutable::parse('2026-07-01 00:00:00'))->status)->toBe(PostStatus::Scheduled)
        ->and(Posts::publish($post)->status)->toBe(PostStatus::Published)
        ->and($post->published_at?->toDateTimeString())->toBe('2026-06-06 06:00:00')
        // Already published: kept as it is — no re-date, no second event.
        ->and(Posts::publish($post, CarbonImmutable::parse('2026-06-01 00:00:00'))->published_at?->toDateTimeString())->toBe('2026-06-06 06:00:00')
        ->and(Posts::archive($post)->status)->toBe(PostStatus::Archived)
        ->and(Posts::unpublish($post)->status)->toBe(PostStatus::Draft)
        ->and($post->fresh()?->status)->toBe(PostStatus::Draft);

    Event::assertDispatched(PostScheduled::class);
    Event::assertDispatchedTimes(PostPublished::class, 1);
    Event::assertDispatched(PostArchived::class);
    Event::assertDispatched(PostDrafted::class);
});

it('refuses to publish an archived post through the facade', function (): void {
    Posts::publish(Post::factory()->archived()->create());
})->throws(InvalidPostStatusTransitionException::class);

it('updates seo through the facade', function (): void {
    $post = Post::factory()->create();

    Posts::seo($post, new SeoData(metaTitle: 'SEO', canonical: 'https://example.test/seo'));

    $fresh = $post->fresh();

    expect($fresh?->getTranslation('meta_title', 'en'))->toBe('SEO')
        ->and($fresh?->seo()->canonical)->toBe('https://example.test/seo');
});

it('syncs tags through the facade', function (): void {
    $post = Post::factory()->create();
    $tag = Tag::factory()->create(['name' => ['en' => 'eloquent']]);

    expect(Posts::syncTags($post, ['php', $tag]))->toBe($post)
        ->and($post->tags()->count())->toBe(2);
});

it('publishes due posts through the facade', function (): void {
    Post::factory()->count(2)->create(['status' => PostStatus::Scheduled, 'published_at' => now()->subMinute()]);

    expect(Posts::publishDue())->toBe(2)
        ->and(Posts::publishDue())->toBe(0);
});

it('finds a post by slug through the facade', function (): void {
    $post = Post::factory()->withTitles(['en' => 'Hello world', 'sk' => 'Ahoj svet'])->create();

    expect(Posts::findBySlug('hello-world')?->is($post))->toBeTrue()
        ->and(Posts::findBySlug('ahoj-svet')?->is($post))->toBeTrue()
        ->and(Posts::findBySlug('ahoj-svet', 'sk')?->is($post))->toBeTrue()
        ->and(Posts::findBySlug('ahoj-svet', 'en'))->toBeNull()
        ->and(Posts::findBySlug('missing'))->toBeNull();
});

it('queries published posts and all posts through the facade', function (): void {
    $published = Post::factory()->published()->create();
    Post::factory()->draft()->create();
    Post::factory()->create(['status' => PostStatus::Published, 'published_at' => now()->addDay()]);

    expect(Posts::published()->pluck('id')->all())->toBe([$published->id])
        ->and(Posts::query()->count())->toBe(3)
        ->and(Posts::query()->getModel())->toBeInstanceOf(Post::class);
});

it('resolves the same api from the container', function (): void {
    $manager = app(PostsManager::class);

    $post = $manager->draft()->title('en', 'Injected')->publish();

    expect($manager)->toBe(app(PostsManager::class))
        ->and($post->fresh()?->status)->toBe(PostStatus::Published)
        ->and($manager->findBySlug('injected')?->is($post))->toBeTrue();
});

it('routes the model lifecycle methods through the manager', function (): void {
    $post = Post::factory()->draft()->create();

    expect($post->schedule(now()->addDay()))->toBe($post)
        ->and($post->publish())->toBe($post)
        ->and($post->archive())->toBe($post)
        ->and($post->unpublish())->toBe($post)
        ->and($post->syncTags(['php']))->toBe($post)
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->tags()->count())->toBe(1);
});
