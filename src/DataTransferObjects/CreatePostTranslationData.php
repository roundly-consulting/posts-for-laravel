<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\DataTransferObjects;

final readonly class CreatePostTranslationData
{
    public function __construct(
        public string $locale,
        public ?string $title = null,
        public ?string $slug = null,
        public ?string $perex = null,
        public ?string $content = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
    ) {}
}
