<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Testing;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Assert as PHPUnit;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\PostsManager;
use RoundlyConsulting\Posts\Support\PostHydrator;

/**
 * Test double for the posts manager, installed by `Posts::fake()`. It extends the manager, so
 * injected managers keep type-checking, and it records every write instead of running it —
 * whether it arrives through the facade, an injected manager, the `draft()` builder, the model's
 * `publish()`/`schedule()`/`archive()`/`unpublish()`/`syncTags()`, the moderation listener or
 * `posts:publish-scheduled`. Nothing is written and no event fires: `create()` returns an
 * unsaved post, the other writes return the post untouched. Reads (`findBySlug()`,
 * `published()`, `query()`) still hit the database.
 */
final class PostsFake extends PostsManager
{
    /** @var list<CreatePostData> */
    private array $created = [];

    /** @var list<RecordedPostCall> */
    private array $published = [];

    /** @var list<RecordedPostCall> */
    private array $scheduled = [];

    /** @var list<RecordedPostCall> */
    private array $archived = [];

    /** @var list<RecordedPostCall> */
    private array $unpublished = [];

    /** @var list<RecordedPostCall> */
    private array $seoUpdates = [];

    /** @var list<RecordedPostCall> */
    private array $tagged = [];

    private int $publishDueCalls = 0;

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function create(CreatePostData $data): Post
    {
        $this->created[] = $data;

        return PostHydrator::hydrate($data);
    }

    public function publish(Post $post, ?CarbonInterface $at = null): Post
    {
        $this->published[] = new RecordedPostCall($post, at: $at);

        return $post;
    }

    public function schedule(Post $post, CarbonInterface $at): Post
    {
        $this->scheduled[] = new RecordedPostCall($post, at: $at);

        return $post;
    }

    public function archive(Post $post): Post
    {
        $this->archived[] = new RecordedPostCall($post);

        return $post;
    }

    public function unpublish(Post $post): Post
    {
        $this->unpublished[] = new RecordedPostCall($post);

        return $post;
    }

    public function seo(Post $post, SeoData $data): Post
    {
        $this->seoUpdates[] = new RecordedPostCall($post, seo: $data);

        return $post;
    }

    /**
     * @param  iterable<int, string|Tag>  $tags
     */
    public function syncTags(Post $post, iterable $tags): Post
    {
        $list = [];

        foreach ($tags as $tag) {
            $list[] = $tag;
        }

        $this->tagged[] = new RecordedPostCall($post, tags: $list);

        return $post;
    }

    public function publishDue(): int
    {
        $this->publishDueCalls++;

        // Nothing ran, so nothing was published.
        return 0;
    }

    /**
     * @param  (Closure(CreatePostData): bool)|null  $callback  when given, at least one created post must pass it
     */
    public function assertCreated(?Closure $callback = null): void
    {
        $matching = $callback === null
            ? $this->created
            : array_filter($this->created, $callback);

        PHPUnit::assertNotEmpty($matching, $callback === null
            ? 'Expected a post to be created.'
            : 'Expected a post matching the callback to be created.');
    }

    public function assertNothingCreated(): void
    {
        PHPUnit::assertSame([], $this->created, sprintf('Expected no post to be created, but %d were.', count($this->created)));
    }

    /**
     * @param  CarbonInterface|null  $at  when given, the publish date passed must equal it
     */
    public function assertPublished(Post $post, ?CarbonInterface $at = null): void
    {
        PHPUnit::assertTrue(
            $this->recorded($this->published, $post, $at),
            'Expected the post to be published'.$this->describeAt($at).'.',
        );
    }

    public function assertNothingPublished(): void
    {
        PHPUnit::assertSame([], $this->published, 'Expected no post to be published.');
    }

    /**
     * @param  CarbonInterface|null  $at  when given, the scheduled date must equal it
     */
    public function assertScheduled(Post $post, ?CarbonInterface $at = null): void
    {
        PHPUnit::assertTrue(
            $this->recorded($this->scheduled, $post, $at),
            'Expected the post to be scheduled'.$this->describeAt($at).'.',
        );
    }

    public function assertNothingScheduled(): void
    {
        PHPUnit::assertSame([], $this->scheduled, 'Expected no post to be scheduled.');
    }

    public function assertArchived(Post $post): void
    {
        PHPUnit::assertTrue($this->recorded($this->archived, $post), 'Expected the post to be archived.');
    }

    public function assertNothingArchived(): void
    {
        PHPUnit::assertSame([], $this->archived, 'Expected no post to be archived.');
    }

    public function assertUnpublished(Post $post): void
    {
        PHPUnit::assertTrue($this->recorded($this->unpublished, $post), 'Expected the post to be unpublished.');
    }

    public function assertNothingUnpublished(): void
    {
        PHPUnit::assertSame([], $this->unpublished, 'Expected no post to be unpublished.');
    }

    /**
     * @param  (Closure(SeoData): bool)|null  $callback  when given, the SEO data must pass it
     */
    public function assertSeoUpdated(Post $post, ?Closure $callback = null): void
    {
        $matching = array_filter(
            $this->seoUpdates,
            static fn (RecordedPostCall $call): bool => $call->targets($post)
                && ($callback === null || ($call->seo !== null && $callback($call->seo) === true)),
        );

        PHPUnit::assertNotEmpty($matching, 'Expected the post SEO to be updated'.($callback === null ? '' : ' with matching data').'.');
    }

    public function assertNothingSeoUpdated(): void
    {
        PHPUnit::assertSame([], $this->seoUpdates, 'Expected no post SEO to be updated.');
    }

    /**
     * @param  list<string>|null  $tags  when given, the synced tag names must be exactly these
     */
    public function assertTagged(Post $post, ?array $tags = null): void
    {
        $matching = array_filter(
            $this->tagged,
            static fn (RecordedPostCall $call): bool => $call->targets($post) && $call->tagged($tags),
        );

        PHPUnit::assertNotEmpty($matching, 'Expected the post tags to be synced'
            .($tags === null ? '' : ' to ['.implode(', ', $tags).']').'.');
    }

    public function assertNothingTagged(): void
    {
        PHPUnit::assertSame([], $this->tagged, 'Expected no post tags to be synced.');
    }

    public function assertPublishedDue(): void
    {
        PHPUnit::assertGreaterThan(0, $this->publishDueCalls, 'Expected due posts to be published.');
    }

    public function assertNothingPublishedDue(): void
    {
        PHPUnit::assertSame(0, $this->publishDueCalls, 'Expected due posts not to be published.');
    }

    /**
     * @param  list<RecordedPostCall>  $calls
     */
    private function recorded(array $calls, Post $post, ?CarbonInterface $at = null): bool
    {
        foreach ($calls as $call) {
            if ($call->targets($post) && $call->at($at)) {
                return true;
            }
        }

        return false;
    }

    private function describeAt(?CarbonInterface $at): string
    {
        return $at === null ? '' : " at [{$at->toIso8601String()}]";
    }
}
