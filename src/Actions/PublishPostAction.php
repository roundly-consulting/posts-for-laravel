<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use Carbon\CarbonInterface;
use RoundlyConsulting\Posts\Models\Post;

final class PublishPostAction
{
    public function execute(Post $post, ?CarbonInterface $at = null): Post
    {
        return $post->publish($at);
    }
}
