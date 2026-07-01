<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Tests\Support\ReaderTestModel;
use RoundlyConsulting\Reports\Contracts\Reportable;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Exceptions\DuplicateReportException;
use RoundlyConsulting\Reports\Exceptions\UnknownReportReasonException;
use RoundlyConsulting\Reports\Facades\Reports;

it('is a reportable subject', function (): void {
    expect(Post::factory()->create())->toBeInstanceOf(Reportable::class);
});

it('files a report against a post morphed to it', function (): void {
    $post = Post::factory()->create();
    $user = ReaderTestModel::create(['name' => 'Ada']);

    $report = Reports::report($post)
        ->by($user)
        ->for(Reason::Spam)
        ->because('Obvious spam.')
        ->create();

    expect($report->reported()->is($post))->toBeTrue()
        ->and($report->reason)->toBe(Reason::Spam->value)
        ->and($post->hasBeenReported())->toBeTrue()
        ->and($post->isReportedBy($user))->toBeTrue()
        ->and($post->reportsCount())->toBe(1)
        ->and($post->reportsCount(Status::Pending))->toBe(1);
});

it('rejects a duplicate report by the same reporter', function (): void {
    $post = Post::factory()->create();
    $user = ReaderTestModel::create(['name' => 'Ada']);

    Reports::report($post)->by($user)->for(Reason::Spam)->create();

    Reports::report($post)->by($user)->for(Reason::Abuse)->create();
})->throws(DuplicateReportException::class);

it('rejects an unknown reason', function (): void {
    $post = Post::factory()->create();
    $user = ReaderTestModel::create(['name' => 'Ada']);

    Reports::report($post)->by($user)->for('not-a-reason')->create();
})->throws(UnknownReportReasonException::class);

it('accepts a guest report', function (): void {
    $post = Post::factory()->create();

    $report = Reports::report($post)
        ->asGuest(hash('sha256', '203.0.113.4'))
        ->for('spam')
        ->because('Guest flag.')
        ->create();

    expect($report->guest_identifier)->not->toBeNull()
        ->and($post->hasBeenReported())->toBeTrue();
});

it('surfaces a moderation queue via report-count scopes', function (): void {
    $hot = Post::factory()->create();
    $mild = Post::factory()->create();
    $clean = Post::factory()->create();

    foreach (range(1, 3) as $i) {
        Reports::report($hot)->by(ReaderTestModel::create(['name' => "H{$i}"]))->for('spam')->create();
    }

    Reports::report($mild)->by(ReaderTestModel::create(['name' => 'M1']))->for('spam')->create();

    $queue = Post::query()->mostReported()->get();

    expect($queue->first()->is($hot))->toBeTrue()
        ->and((int) $queue->first()->reports_count)->toBe(3);

    $overThreshold = Post::query()->reportedMoreThan(2)->get();

    expect($overThreshold->pluck('id')->all())->toBe([$hot->getKey()])
        ->and($clean->hasBeenReported())->toBeFalse();

    $counted = Post::query()->withReportCounts()->whereKey($mild->getKey())->first();

    expect((int) $counted->reports_count)->toBe(1);
});
