<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\DataTransferObjects;

final readonly class SeoData
{
    public function __construct(
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $canonical = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public ?string $ogType = null,
        public ?string $twitterCard = null,
        public ?string $twitterSite = null,
        public ?string $twitterCreator = null,
        public ?string $robots = null,
        public ?string $ogSiteName = null,
    ) {}

    /**
     * Build the DTO from the non-translatable SEO bag stored on the post.
     *
     * @param  array<string, mixed>  $bag
     */
    public static function fromBag(array $bag, ?string $metaTitle = null, ?string $metaDescription = null): self
    {
        return new self(
            metaTitle: $metaTitle,
            metaDescription: $metaDescription,
            canonical: self::stringOrNull($bag['canonical'] ?? null),
            ogTitle: self::stringOrNull($bag['og_title'] ?? null),
            ogDescription: self::stringOrNull($bag['og_description'] ?? null),
            ogImage: self::stringOrNull($bag['og_image'] ?? null),
            ogType: self::stringOrNull($bag['og_type'] ?? null),
            twitterCard: self::stringOrNull($bag['twitter_card'] ?? null),
            twitterSite: self::stringOrNull($bag['twitter_site'] ?? null),
            twitterCreator: self::stringOrNull($bag['twitter_creator'] ?? null),
            robots: self::stringOrNull($bag['robots'] ?? null),
            ogSiteName: self::stringOrNull($bag['og_site_name'] ?? null),
        );
    }

    /**
     * The non-translatable SEO fields to persist in the post's "seo" column.
     *
     * @return array<string, string|null>
     */
    public function toBag(): array
    {
        return [
            'canonical' => $this->canonical,
            'og_title' => $this->ogTitle,
            'og_description' => $this->ogDescription,
            'og_image' => $this->ogImage,
            'og_type' => $this->ogType,
            'twitter_card' => $this->twitterCard,
            'twitter_site' => $this->twitterSite,
            'twitter_creator' => $this->twitterCreator,
            'robots' => $this->robots,
            'og_site_name' => $this->ogSiteName,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
