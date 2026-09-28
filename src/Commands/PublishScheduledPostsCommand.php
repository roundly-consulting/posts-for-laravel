<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Posts\PostsManager;

final class PublishScheduledPostsCommand extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Publish scheduled posts whose publish time has arrived';

    public function handle(PostsManager $posts): int
    {
        $count = $posts->publishDue();

        $this->info("Published {$count} scheduled post(s).");

        return self::SUCCESS;
    }
}
