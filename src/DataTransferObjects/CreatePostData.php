<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\DataTransferObjects;

use Carbon\CarbonInterface;
use RoundlyConsulting\Posts\Enums\PostStatus;

final readonly class CreatePostData
{
    /**
     * @param  list<CreatePostTranslationData>  $translations
     */
    public function __construct(
        public array $translations,
        public PostStatus $status = PostStatus::Draft,
        public ?CarbonInterface $publishedAt = null,
        public ?string $authorType = null,
        public int|string|null $authorId = null,
        public ?SeoData $seo = null,
    ) {}
}
