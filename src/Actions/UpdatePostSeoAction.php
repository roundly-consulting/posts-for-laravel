<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Actions;

use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Post;

final readonly class UpdatePostSeoAction
{
    /**
     * Store the SEO fields and save: meta title/description go to the current locale, the rest
     * replace the post's `seo` bag.
     */
    public function execute(Post $post, SeoData $data): Post
    {
        $post->setSeo($data);
        $post->save();

        return $post;
    }
}
