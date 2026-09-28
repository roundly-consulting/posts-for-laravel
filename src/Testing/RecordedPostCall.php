<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Testing;

use Carbon\CarbonInterface;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;

/**
 * One call captured by {@see PostsFake}: the post it targeted, plus the date, SEO data or tags
 * it carried.
 */
final readonly class RecordedPostCall
{
    /**
     * @param  list<string|Tag>  $tags
     */
    public function __construct(
        public Post $post,
        public ?CarbonInterface $at = null,
        public ?SeoData $seo = null,
        public array $tags = [],
    ) {}

    /**
     * The same post: the very instance, or — once saved — the same row.
     */
    public function targets(Post $post): bool
    {
        return $this->post === $post || ($this->post->exists && $post->exists && $this->post->is($post));
    }

    public function at(?CarbonInterface $at): bool
    {
        return $at === null || ($this->at !== null && $this->at->equalTo($at));
    }

    /**
     * Whether the synced tags name exactly these (tag models by their current-locale name).
     *
     * @param  list<string>|null  $names
     */
    public function tagged(?array $names): bool
    {
        if ($names === null) {
            return true;
        }

        $synced = array_map(
            static fn (string|Tag $tag): string => $tag instanceof Tag ? $tag->translate('name') : $tag,
            $this->tags,
        );

        sort($synced);
        sort($names);

        return $synced === $names;
    }
}
