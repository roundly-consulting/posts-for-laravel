<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Support\PostModel;

final class PublishScheduledPostsCommand extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Publish scheduled posts whose publish time has arrived';

    public function handle(): int
    {
        $due = PostModel::query()
            ->where('status', PostStatus::Scheduled)
            ->where('published_at', '<=', now())
            ->get();

        foreach ($due as $post) {
            $post->publish($post->published_at);
        }

        $this->info("Published {$due->count()} scheduled post(s).");

        return self::SUCCESS;
    }
}
