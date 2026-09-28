<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostTranslationData;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\PostsManager;

/**
 * A post being written — `Posts::draft()`. Collect per-locale fields, the author, tags and SEO,
 * then finish with `save()` (a draft), `publish(?$at)` or `schedule($at)`.
 *
 * Every terminal goes through the manager (create, then syncTags / publish / schedule), so a
 * faked manager records each step and nothing is written.
 */
final class PendingPost
{
    /** @var array<string, array<string, string>> locale => field => value */
    private array $fields = [];

    private ?Model $author = null;

    /** @var list<string|Tag> */
    private array $tags = [];

    private ?SeoData $seo = null;

    public function __construct(
        private readonly PostsManager $posts,
    ) {}

    public function title(string $locale, string $title): self
    {
        return $this->set($locale, 'title', $title);
    }

    /**
     * A manual slug; it is normalised and made unique. Omit it to generate one from the title.
     */
    public function slug(string $locale, string $slug): self
    {
        return $this->set($locale, 'slug', $slug);
    }

    public function perex(string $locale, string $perex): self
    {
        return $this->set($locale, 'perex', $perex);
    }

    /**
     * The body HTML. It is stored and rendered as is — sanitise untrusted input before this.
     */
    public function content(string $locale, string $content): self
    {
        return $this->set($locale, 'content', $content);
    }

    public function metaTitle(string $locale, string $metaTitle): self
    {
        return $this->set($locale, 'metaTitle', $metaTitle);
    }

    public function metaDescription(string $locale, string $metaDescription): self
    {
        return $this->set($locale, 'metaDescription', $metaDescription);
    }

    /**
     * The post's author (any model, bigint/uuid/ulid keyed).
     */
    public function by(Model $author): self
    {
        $this->author = $author;

        return $this;
    }

    /**
     * Tags to attach: `Tag` models, or names found or created in the current locale.
     *
     * @param  iterable<int, string|Tag>  $tags
     */
    public function tags(iterable $tags): self
    {
        foreach ($tags as $tag) {
            $this->tags[] = $tag;
        }

        return $this;
    }

    public function seo(SeoData $seo): self
    {
        $this->seo = $seo;

        return $this;
    }

    /**
     * Save the post as a draft.
     */
    public function save(): Post
    {
        $post = $this->posts->create($this->data());

        if ($this->tags !== []) {
            $this->posts->syncTags($post, $this->tags);
        }

        return $post;
    }

    /**
     * Save the post and publish it at `$at` (now when omitted).
     */
    public function publish(?CarbonInterface $at = null): Post
    {
        return $this->posts->publish($this->save(), $at);
    }

    /**
     * Save the post and schedule it to go live at `$at`.
     */
    public function schedule(CarbonInterface $at): Post
    {
        return $this->posts->schedule($this->save(), $at);
    }

    /**
     * The DTO the builder would create — for handing to a queued job or {@see PostsManager::create()}.
     */
    public function data(): CreatePostData
    {
        $translations = [];

        foreach ($this->fields as $locale => $fields) {
            $translations[] = new CreatePostTranslationData(
                locale: $locale,
                title: $fields['title'] ?? null,
                slug: $fields['slug'] ?? null,
                perex: $fields['perex'] ?? null,
                content: $fields['content'] ?? null,
                metaTitle: $fields['metaTitle'] ?? null,
                metaDescription: $fields['metaDescription'] ?? null,
            );
        }

        return new CreatePostData(
            translations: $translations,
            authorType: $this->author?->getMorphClass(),
            authorId: $this->authorKey(),
            seo: $this->seo,
        );
    }

    private function set(string $locale, string $field, string $value): self
    {
        $this->fields[$locale][$field] = $value;

        return $this;
    }

    private function authorKey(): int|string|null
    {
        $key = $this->author?->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }
}
