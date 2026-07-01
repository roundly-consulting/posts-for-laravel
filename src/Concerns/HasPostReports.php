<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Concerns;

use RoundlyConsulting\Reports\Traits\HasReports;

/**
 * First-class report-a-post moderation for the bundled Post model, built on
 * roundly-consulting/reports-for-laravel.
 *
 * Wires the post into reports' `HasReports` seam so a post can be flagged (with
 * dedup, typed reasons and guest reports), counted per status, and surfaced in a
 * moderation queue (`mostReported()`, `reportedMoreThan()`, `withReportCounts()`).
 * Because reports routes resolution through approvals, posts inherit
 * multi-moderator sign-off with no extra code.
 *
 * Reporters are always passed explicitly (e.g.
 * `Reports::report($post)->by($user)->create()`); this package never resolves the
 * reporter implicitly from the auth guard.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasPostReports
{
    use HasReports;
}
