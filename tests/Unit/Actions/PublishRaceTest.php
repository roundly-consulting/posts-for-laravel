<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostArchived;
use RoundlyConsulting\Posts\Events\PostDrafted;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Exceptions\InvalidPostStatusTransitionException;
use RoundlyConsulting\Posts\Facades\Posts;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Testing\Fixtures\LockRecorder;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-09-28 12:00:00'));

afterEach(fn () => CarbonImmutable::setTestNow());

/** @return list<Post> */
function duePosts(int $count): array
{
    return Post::factory()->count($count)
        ->create(['status' => PostStatus::Scheduled, 'published_at' => now()->subHour()])
        ->all();
}

it('publishes each due post once when two runs overlap', function (): void {
    [$first, $second] = duePosts(2);
    $fired = [];
    $inner = null;

    // The second run starts while the first is mid-loop, holding both posts in memory.
    Event::listen(PostPublished::class, function (PostPublished $event) use (&$fired, &$inner): void {
        $fired[] = $event->postId;

        if ($inner === null) {
            $inner = Posts::publishDue();
        }
    });

    $outer = Posts::publishDue();

    expect($fired)->toBe([$first->getKey(), $second->getKey()])
        ->and($outer + $inner)->toBe(2)
        ->and($second->fresh()?->published_at?->toDateTimeString())->toBe('2026-09-28 11:00:00');
});

it('does not publish a post archived after the run picked it up', function (): void {
    [$first, $second] = duePosts(2);

    Event::listen(PostPublished::class, function (PostPublished $event) use ($first, $second): void {
        if ($event->postId === $first->getKey()) {
            Post::query()->findOrFail($second->getKey())->archive();
        }
    });

    expect(Posts::publishDue())->toBe(1)
        ->and($second->fresh()?->status)->toBe(PostStatus::Archived);
});

it('does not publish a post drafted or rescheduled after the run picked it up', function (): void {
    [$first, $drafted, $rescheduled] = duePosts(3);

    Event::listen(PostPublished::class, function (PostPublished $event) use ($first, $drafted, $rescheduled): void {
        if ($event->postId === $first->getKey()) {
            Post::query()->findOrFail($drafted->getKey())->unpublish();
            Post::query()->findOrFail($rescheduled->getKey())->schedule(now()->addDay());
        }
    });

    expect(Posts::publishDue())->toBe(1)
        ->and($drafted->fresh()?->status)->toBe(PostStatus::Draft)
        ->and($rescheduled->fresh()?->status)->toBe(PostStatus::Scheduled);
});

it('publishes a post rescheduled meanwhile at its stored date, not the loaded one', function (): void {
    [$first, $second] = duePosts(2);

    Event::listen(PostPublished::class, function (PostPublished $event) use ($first, $second): void {
        if ($event->postId === $first->getKey()) {
            Post::query()->findOrFail($second->getKey())->schedule(now()->subMinutes(30));
        }
    });

    expect(Posts::publishDue())->toBe(2)
        ->and($second->fresh()?->published_at?->toDateTimeString())->toBe('2026-09-28 11:30:00');
});

it('keeps the date and fires nothing when publishing an already-published post', function (): void {
    $post = Post::factory()->published()->create();
    $copy = Post::query()->findOrFail($post->getKey());

    CarbonImmutable::setTestNow('2026-10-05 12:00:00');
    Event::fake([PostPublished::class]);

    Posts::publish($post);
    $copy->publish(now());

    expect($post->fresh()?->published_at?->toDateTimeString())->toBe('2026-09-28 12:00:00')
        ->and($copy->published_at?->toDateTimeString())->toBe('2026-09-28 12:00:00');
    Event::assertNotDispatched(PostPublished::class);
});

it('refuses to publish a stale copy of a post archived meanwhile', function (): void {
    $post = Post::factory()->draft()->create();
    $stale = Post::query()->findOrFail($post->getKey());

    $post->publish();
    $post->archive();

    expect(fn () => $stale->publish())->toThrow(InvalidPostStatusTransitionException::class)
        ->and($post->fresh()?->status)->toBe(PostStatus::Archived);
});

it('fires archive and draft events once for concurrent moderators', function (): void {
    Event::fake([PostArchived::class, PostDrafted::class]);

    $post = Post::factory()->published()->create();
    $a = Post::query()->findOrFail($post->getKey());
    $b = Post::query()->findOrFail($post->getKey());

    $a->archive();
    $b->archive();

    Event::assertDispatchedTimes(PostArchived::class, 1);

    $a->unpublish();
    $b->unpublish();

    Event::assertDispatchedTimes(PostDrafted::class, 1);
});

it('decides under a row lock taken inside the write transaction', function (): void {
    $connection = DB::connection();
    $connection->setQueryGrammar(new LockRecordingGrammar($connection));
    LockRecorder::flush();
    LockRecorder::listenForMarkers();

    [$due] = duePosts(1);
    $draft = Post::factory()->draft()->create();
    LockRecorder::flush();

    Posts::publish($draft);

    expect(LockRecorder::recorded())->toHaveCount(1)
        ->and(LockRecorder::recorded()[0]['transactionDepth'])->toBe(1)
        ->and(LockRecorder::recorded()[0]['sql'])->not->toContain('count(')->toContain('where "posts"."id" = ?');

    LockRecorder::flush();

    expect(Posts::publishDue())->toBe(1);

    // The claim (still scheduled, still due) at depth 1 holds through the publish's own re-check.
    $locks = LockRecorder::recorded();

    expect($locks)->toHaveCount(2)
        ->and($locks[0]['transactionDepth'])->toBe(1)
        ->and($locks[0]['sql'])->toContain('"status" = ?')->toContain('"published_at" <= ?')
        ->and($locks[1]['transactionDepth'])->toBe(2)
        ->and($due->fresh()?->status)->toBe(PostStatus::Published);
});
