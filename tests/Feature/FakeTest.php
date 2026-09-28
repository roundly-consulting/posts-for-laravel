<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostTranslationData;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Facades\Posts;
use RoundlyConsulting\Posts\Listeners\SyncPostVisibilityFromReports;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\PostsManager;
use RoundlyConsulting\Posts\Testing\PostsFake;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;
use RoundlyConsulting\Reports\Models\Report;

it('installs a fake subtype of the manager behind the facade and the container', function (): void {
    $fake = Posts::fake();

    expect($fake)->toBeInstanceOf(PostsFake::class)
        ->and($fake)->toBeInstanceOf(PostsManager::class)
        ->and(app(PostsManager::class))->toBe($fake);
});

it('records a create without writing anything', function (): void {
    Event::fake([PostPublished::class]);
    Posts::fake();

    $post = Posts::create(new CreatePostData(
        translations: [new CreatePostTranslationData(locale: 'en', title: 'Faked')],
        status: PostStatus::Published,
    ));

    expect($post->exists)->toBeFalse()
        ->and($post->getTranslation('title', 'en'))->toBe('Faked')
        ->and($post->published_at)->not->toBeNull()
        ->and(Post::query()->count())->toBe(0);

    Posts::assertCreated();
    Posts::assertCreated(fn (CreatePostData $data): bool => $data->translations[0]->title === 'Faked');
    Event::assertNotDispatched(PostPublished::class);
});

it('fails assertCreated when nothing matches', function (): void {
    Posts::fake();

    expect(fn () => Posts::assertCreated())->toThrow(AssertionFailedError::class, 'Expected a post to be created.');

    Posts::create(new CreatePostData(translations: [new CreatePostTranslationData(locale: 'en', title: 'One')]));

    expect(fn () => Posts::assertCreated(fn (CreatePostData $data): bool => false))
        ->toThrow(AssertionFailedError::class, 'Expected a post matching the callback to be created.');
});

it('asserts nothing was created', function (): void {
    Posts::fake();

    Posts::assertNothingCreated();

    Posts::draft()->title('en', 'Draft')->save();

    expect(fn () => Posts::assertNothingCreated())->toThrow(AssertionFailedError::class, 'Expected no post to be created, but 1 were.');
});

it('records the builder terminals step by step', function (): void {
    $fake = Posts::fake();
    $at = CarbonImmutable::parse('2031-01-01 00:00:00');

    $draft = Posts::draft()->title('en', 'Draft')->tags(['php'])->save();
    $published = Posts::draft()->title('en', 'Now')->publish($at);
    $scheduled = Posts::draft()->title('en', 'Later')->schedule($at);

    $fake->assertCreated(fn (CreatePostData $data): bool => $data->translations[0]->title === 'Later');
    $fake->assertTagged($draft, ['php']);
    $fake->assertPublished($published, $at);
    $fake->assertScheduled($scheduled, $at);

    expect(Post::query()->count())->toBe(0)
        ->and(Tag::query()->count())->toBe(0)
        // Unsaved posts match by identity, not by their (empty) key.
        ->and(fn () => $fake->assertPublished($draft))->toThrow(AssertionFailedError::class);
});

it('asserts publishing', function (): void {
    Posts::fake();
    $post = Post::factory()->draft()->create();
    $other = Post::factory()->draft()->create();

    Posts::assertNothingPublished();

    Posts::publish($post, CarbonImmutable::parse('2026-01-01 00:00:00'));

    Posts::assertPublished($post);
    Posts::assertPublished($post->fresh() ?? $post, CarbonImmutable::parse('2026-01-01 00:00:00'));

    expect($post->fresh()?->status)->toBe(PostStatus::Draft)
        ->and(fn () => Posts::assertPublished($other))->toThrow(AssertionFailedError::class, 'Expected the post to be published.')
        ->and(fn () => Posts::assertPublished($post, CarbonImmutable::parse('2026-02-02 00:00:00')))
        ->toThrow(AssertionFailedError::class, 'Expected the post to be published at [2026-02-02T00:00:00+00:00].')
        ->and(fn () => Posts::assertNothingPublished())->toThrow(AssertionFailedError::class, 'Expected no post to be published.');
});

it('asserts publishing without a date only when given no date', function (): void {
    Posts::fake();
    $post = Post::factory()->draft()->create();

    Posts::publish($post);

    Posts::assertPublished($post);

    expect(fn () => Posts::assertPublished($post, CarbonImmutable::parse('2026-01-01 00:00:00')))
        ->toThrow(AssertionFailedError::class);
});

it('asserts scheduling', function (): void {
    Posts::fake();
    $post = Post::factory()->draft()->create();
    $at = CarbonImmutable::parse('2031-01-01 00:00:00');

    Posts::assertNothingScheduled();

    Posts::schedule($post, $at);

    Posts::assertScheduled($post, $at);

    expect(fn () => Posts::assertScheduled(Post::factory()->create()))->toThrow(AssertionFailedError::class, 'Expected the post to be scheduled.')
        ->and(fn () => Posts::assertNothingScheduled())->toThrow(AssertionFailedError::class, 'Expected no post to be scheduled.');
});

it('asserts archiving', function (): void {
    Posts::fake();
    $post = Post::factory()->published()->create();

    Posts::assertNothingArchived();

    Posts::archive($post);

    Posts::assertArchived($post);

    expect($post->fresh()?->status)->toBe(PostStatus::Published)
        ->and(fn () => Posts::assertArchived(Post::factory()->create()))->toThrow(AssertionFailedError::class, 'Expected the post to be archived.')
        ->and(fn () => Posts::assertNothingArchived())->toThrow(AssertionFailedError::class, 'Expected no post to be archived.');
});

it('asserts unpublishing', function (): void {
    Posts::fake();
    $post = Post::factory()->published()->create();

    Posts::assertNothingUnpublished();

    Posts::unpublish($post);

    Posts::assertUnpublished($post);

    expect(fn () => Posts::assertUnpublished(Post::factory()->create()))->toThrow(AssertionFailedError::class, 'Expected the post to be unpublished.')
        ->and(fn () => Posts::assertNothingUnpublished())->toThrow(AssertionFailedError::class, 'Expected no post to be unpublished.');
});

it('asserts seo updates', function (): void {
    Posts::fake();
    $post = Post::factory()->create();

    Posts::assertNothingSeoUpdated();

    Posts::seo($post, new SeoData(robots: 'noindex'));

    Posts::assertSeoUpdated($post);
    Posts::assertSeoUpdated($post, fn (SeoData $data): bool => $data->robots === 'noindex');

    expect($post->fresh()?->seo()->robots)->toBe('index,follow')
        ->and(fn () => Posts::assertSeoUpdated($post, fn (SeoData $data): bool => $data->robots === 'index'))
        ->toThrow(AssertionFailedError::class, 'Expected the post SEO to be updated with matching data.')
        ->and(fn () => Posts::assertSeoUpdated(Post::factory()->create()))->toThrow(AssertionFailedError::class, 'Expected the post SEO to be updated.')
        ->and(fn () => Posts::assertNothingSeoUpdated())->toThrow(AssertionFailedError::class, 'Expected no post SEO to be updated.');
});

it('asserts tag syncs', function (): void {
    Posts::fake();
    $post = Post::factory()->create();
    $tag = Tag::factory()->create(['name' => ['en' => 'eloquent']]);

    Posts::assertNothingTagged();

    Posts::syncTags($post, ['php', $tag]);

    Posts::assertTagged($post);
    Posts::assertTagged($post, ['eloquent', 'php']);

    expect($post->tags()->count())->toBe(0)
        ->and(fn () => Posts::assertTagged($post, ['php']))->toThrow(AssertionFailedError::class, 'Expected the post tags to be synced to [php].')
        ->and(fn () => Posts::assertTagged(Post::factory()->create()))->toThrow(AssertionFailedError::class, 'Expected the post tags to be synced.')
        ->and(fn () => Posts::assertNothingTagged())->toThrow(AssertionFailedError::class, 'Expected no post tags to be synced.');
});

it('asserts publish-due runs, including from the command', function (): void {
    Posts::fake();
    Post::factory()->create(['status' => PostStatus::Scheduled, 'published_at' => now()->subMinute()]);

    Posts::assertNothingPublishedDue();

    expect(fn () => Posts::assertPublishedDue())->toThrow(AssertionFailedError::class, 'Expected due posts to be published.');

    $this->artisan('posts:publish-scheduled')
        ->expectsOutput('Published 0 scheduled post(s).')
        ->assertSuccessful();

    Posts::assertPublishedDue();

    expect(Post::query()->sole()->status)->toBe(PostStatus::Scheduled)
        ->and(fn () => Posts::assertNothingPublishedDue())->toThrow(AssertionFailedError::class, 'Expected due posts not to be published.');
});

it('records calls made through the model methods', function (): void {
    $fake = Posts::fake();
    $post = Post::factory()->draft()->create();
    $at = CarbonImmutable::parse('2031-01-01 00:00:00');

    $post->schedule($at);
    $post->publish();
    $post->archive();
    $post->unpublish();
    $post->syncTags(['php']);

    $fake->assertScheduled($post, $at);
    $fake->assertPublished($post);
    $fake->assertArchived($post);
    $fake->assertUnpublished($post);
    $fake->assertTagged($post, ['php']);

    expect($post->fresh()?->status)->toBe(PostStatus::Draft)
        ->and(Tag::query()->count())->toBe(0);
});

it('records the moderation listener unpublishing a post', function (): void {
    Posts::fake();
    $post = Post::factory()->published()->create();

    (new SyncPostVisibilityFromReports)->handleThresholdReached(new ReportThresholdReached($post, 5, 5));

    Posts::assertArchived($post);
    Posts::assertNothingUnpublished();
});

it('records the draft moderation mode as an unpublish', function (): void {
    config()->set('posts.moderation.on_resolved', 'draft');
    Posts::fake();
    $post = Post::factory()->published()->create();

    $report = new Report([
        'reported_type' => $post->getMorphClass(),
        'reported_id' => $post->getKey(),
        'reason' => 'spam',
        'status' => Status::Resolved->value,
    ]);
    $report->save();

    (new SyncPostVisibilityFromReports)->handleResolved(new ReportResolved($report));

    Posts::assertUnpublished($post);
    Posts::assertNothingArchived();
});

it('still answers reads under the fake', function (): void {
    $post = Post::factory()->published()->withTitles(['en' => 'Readable'])->create();

    Posts::fake();

    expect(Posts::findBySlug('readable')?->is($post))->toBeTrue()
        ->and(Posts::published()->count())->toBe(1)
        ->and(Posts::query()->count())->toBe(1);
});
