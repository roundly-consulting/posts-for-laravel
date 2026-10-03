<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Listeners;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Support\Config;
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
 * through its own lifecycle (re-emitting `PostArchived`/`PostDrafted`) — the model's
 * `archive()`/`unpublish()`, which route through the posts manager, so a host override
 * runs and `Posts::fake()` records it. Because
 * reports is polymorphic and shared, every subject is guarded against the
 * configured post model before acting; non-post subjects are ignored.
 *
 * All behaviour is config-gated by `posts.moderation`. It acts on a post that is live or on
 * its way there — Published, or Scheduled (which would otherwise go live at its date through
 * `publishDue()`) — and leaves a draft or an archived post untouched.
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
        if (! Config::boolean('posts.moderation.auto_unpublish', true)) {
            return;
        }

        $this->unpublish($event->subject, 'archive');
    }

    private function unpublish(?Model $subject, string $action): void
    {
        $post = $this->resolvePost($subject);

        // A scheduled post is taken off the schedule too: left alone, `publishDue()` would put
        // the moderated content live at its date. Drafts and archived posts are left as they
        // are, so the sync never double-archives or re-drafts.
        if (! $post instanceof Post || ! in_array($post->status, [PostStatus::Published, PostStatus::Scheduled], true)) {
            return;
        }

        match ($action) {
            'draft' => $post->unpublish(),
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
