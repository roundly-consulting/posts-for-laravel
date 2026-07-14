<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Listeners;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Support\PostModel;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;

/**
 * Bridges reports' moderation decisions to a post's publishing lifecycle.
 *
 * When a report against a post is upheld (`ReportResolved`) or a post crosses the
 * open-report threshold (`ReportThresholdReached`), the post is auto-unpublished
 * through its own lifecycle (re-emitting `PostArchived`/`PostDrafted`). Because
 * reports is polymorphic and shared, every subject is guarded against the
 * configured post model before acting; non-post subjects are ignored.
 *
 * All behaviour is config-gated by `posts.moderation`; the transition is
 * idempotent — a post that is not currently Published is left untouched.
 */
final class SyncPostVisibilityFromReports
{
    /**
     * Auto-unpublish a post whose report was upheld, per
     * `posts.moderation.on_resolved` ('archive' | 'draft' | null to disable).
     */
    public function handleResolved(ReportResolved $event): void
    {
        $action = config('posts.moderation.on_resolved', 'archive');

        if (! is_string($action)) {
            return;
        }

        $this->unpublish($event->report->reported, $action);
    }

    /**
     * Auto-unpublish a post that crossed the global `reports.threshold`, when
     * `posts.moderation.auto_unpublish` is enabled. Archives the post.
     */
    public function handleThresholdReached(ReportThresholdReached $event): void
    {
        if (! (bool) config('posts.moderation.auto_unpublish', true)) {
            return;
        }

        $this->unpublish($event->subject, 'archive');
    }

    private function unpublish(?Model $subject, string $action): void
    {
        $post = $this->resolvePost($subject);

        // Only act on a currently-published post so the transition stays
        // idempotent (never double-archives / re-drafts an unpublished post).
        if (! $post instanceof Post || $post->status !== PostStatus::Published) {
            return;
        }

        match ($action) {
            'draft' => $post->draft(),
            'archive' => $post->archive(),
            default => null,
        };
    }

    private function resolvePost(?Model $subject): ?Post
    {
        $model = PostModel::class();

        return $subject instanceof $model ? $subject : null;
    }
}
