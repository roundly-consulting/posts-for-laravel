<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostArchived;
use RoundlyConsulting\Posts\Events\PostDrafted;
use RoundlyConsulting\Posts\Listeners\SyncPostVisibilityFromReports;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Tests\Support\ModeratorTestModel;
use RoundlyConsulting\Posts\Tests\Support\ReaderTestModel;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Exceptions\MissingModeratorsException;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;

function reportAndResolve(Post $post): void
{
    $report = Reports::report($post)
        ->by(ReaderTestModel::create(['name' => 'Ada']))
        ->for('spam')
        ->create();

    Reports::resolve($report);
}

it('archives a published post when a report is upheld', function (): void {
    Event::fake([PostArchived::class]);

    $post = Post::factory()->published()->create();

    reportAndResolve($post);

    expect($post->fresh()->status)->toBe(PostStatus::Archived);
    Event::assertDispatched(PostArchived::class);
});

it('drafts the post instead when configured', function (): void {
    config()->set('posts.moderation.on_resolved', 'draft');
    Event::fake([PostDrafted::class]);

    $post = Post::factory()->published()->create();

    reportAndResolve($post);

    expect($post->fresh()->status)->toBe(PostStatus::Draft);
    Event::assertDispatched(PostDrafted::class);
});

it('leaves the post published when the resolved sync is disabled', function (): void {
    config()->set('posts.moderation.on_resolved', null);

    $post = Post::factory()->published()->create();

    reportAndResolve($post);

    expect($post->fresh()->status)->toBe(PostStatus::Published);
});

it('ignores a non-post subject', function (): void {
    $subject = ReaderTestModel::create(['name' => 'Not a post']);

    $report = Reports::report($subject)
        ->by(ReaderTestModel::create(['name' => 'Ada']))
        ->for('spam')
        ->create();

    Reports::resolve($report);

    expect($report->fresh()->status)->toBe(Status::Resolved)
        ->and($subject->exists)->toBeTrue();
});

it('is idempotent for an already-archived post', function (): void {
    Event::fake([PostArchived::class]);

    $post = Post::factory()->archived()->create();

    reportAndResolve($post);

    expect($post->fresh()->status)->toBe(PostStatus::Archived);
    Event::assertNotDispatched(PostArchived::class);
});

it('archives a post when it crosses the report threshold', function (): void {
    config()->set('reports.threshold', 2);

    $post = Post::factory()->published()->create();

    Reports::report($post)->by(ReaderTestModel::create(['name' => 'A']))->for('spam')->create();
    Reports::report($post)->by(ReaderTestModel::create(['name' => 'B']))->for('spam')->create();

    expect($post->fresh()->status)->toBe(PostStatus::Archived);
});

it('leaves the post published on threshold when auto-unpublish is off', function (): void {
    config()->set('reports.threshold', 2);
    config()->set('posts.moderation.auto_unpublish', false);

    $post = Post::factory()->published()->create();

    Reports::report($post)->by(ReaderTestModel::create(['name' => 'A']))->for('spam')->create();
    Reports::report($post)->by(ReaderTestModel::create(['name' => 'B']))->for('spam')->create();

    expect($post->fresh()->status)->toBe(PostStatus::Published);
});

it('archives the post through a multi-moderator sign-off', function (): void {
    $post = Post::factory()->published()->create();

    $report = Reports::report($post)
        ->by(ReaderTestModel::create(['name' => 'Ada']))
        ->for('spam')
        ->create();

    $alice = ModeratorTestModel::create(['name' => 'Alice']);
    $bob = ModeratorTestModel::create(['name' => 'Bob']);

    Reports::moderate($report)
        ->requiring([$alice, $bob])
        ->rule(ApprovalRule::Quorum)
        ->quorum(2)
        ->open();

    Reports::resolve($report, by: $alice);

    // One of two — quorum not yet met, so the post stays published.
    expect($post->fresh()->status)->toBe(PostStatus::Published);

    Reports::resolve($report, by: $bob, note: 'Spam');

    // Quorum reached → ReportResolved → post archived.
    expect($post->fresh()->status)->toBe(PostStatus::Archived);
});

it('leaves the post published for an unrecognised action', function (): void {
    config()->set('posts.moderation.on_resolved', 'freeze');

    $post = Post::factory()->published()->create();

    reportAndResolve($post);

    expect($post->fresh()->status)->toBe(PostStatus::Published);
});

it('ignores a report whose subject is missing', function (): void {
    $report = new Report(['reason' => 'spam', 'status' => Status::Pending->value]);
    $report->save();

    $listener = new SyncPostVisibilityFromReports;

    $listener->handleResolved(new ReportResolved($report));

    expect($report->reported)->toBeNull();
});

it('surfaces the missing-moderators error unchanged', function (): void {
    $post = Post::factory()->published()->create();

    $report = Reports::report($post)
        ->by(ReaderTestModel::create(['name' => 'Ada']))
        ->for('spam')
        ->create();

    Reports::moderate($report)->open();
})->throws(MissingModeratorsException::class);
