<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Post;

final class UpdatePostSeoAction
{
    public function execute(Post $post, SeoData $data): Post
    {
        $post->setSeo($data);
        $post->save();

        return $post;
    }
}
