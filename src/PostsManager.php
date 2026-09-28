<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Posts\Actions\ArchivePostAction;
use RoundlyConsulting\Posts\Actions\CreatePostAction;
use RoundlyConsulting\Posts\Actions\PublishDuePostsAction;
use RoundlyConsulting\Posts\Actions\PublishPostAction;
use RoundlyConsulting\Posts\Actions\SchedulePostAction;
use RoundlyConsulting\Posts\Actions\SyncPostTagsAction;
use RoundlyConsulting\Posts\Actions\UnpublishPostAction;
use RoundlyConsulting\Posts\Actions\UpdatePostSeoAction;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\Support\PendingPost;
use RoundlyConsulting\Posts\Support\PostModel;

/**
 * The posts API: the root behind the {@see Facades\Posts} facade, and the class to inject when
 * you prefer dependency injection. Every write resolves its action from the container, so host
 * overrides apply; the `draft()` builder, the model's `publish()`/`schedule()`/`archive()`/
 * `unpublish()`/`syncTags()`, the moderation listener and `posts:publish-scheduled` all funnel
 * through here.
 *
 * Not final on purpose: {@see Testing\PostsFake} extends it so a constructor-injected manager
 * receives the fake under `Posts::fake()`.
 */
class PostsManager
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * Create a post from a DTO, as the configured `posts.model`.
     */
    public function create(CreatePostData $data): Post
    {
        return $this->container->make(CreatePostAction::class)->execute($data);
    }

    /**
     * Start a post; finish it with `->save()`, `->publish(?$at)` or `->schedule($at)`.
     */
    public function draft(): PendingPost
    {
        return new PendingPost($this);
    }

    /**
     * Publish the post at `$at` (now when omitted).
     */
    public function publish(Post $post, ?CarbonInterface $at = null): Post
    {
        return $this->container->make(PublishPostAction::class)->execute($post, $at);
    }

    /**
     * Schedule the post to go live at `$at`.
     */
    public function schedule(Post $post, CarbonInterface $at): Post
    {
        return $this->container->make(SchedulePostAction::class)->execute($post, $at);
    }

    public function archive(Post $post): Post
    {
        return $this->container->make(ArchivePostAction::class)->execute($post);
    }

    /**
     * Move the post back to draft.
     */
    public function unpublish(Post $post): Post
    {
        return $this->container->make(UnpublishPostAction::class)->execute($post);
    }

    /**
     * Store the post's SEO fields and save it.
     */
    public function seo(Post $post, SeoData $data): Post
    {
        return $this->container->make(UpdatePostSeoAction::class)->execute($post, $data);
    }

    /**
     * Sync the post's tags; strings are found or created by their current-locale name.
     *
     * @param  iterable<int, string|Tag>  $tags
     */
    public function syncTags(Post $post, iterable $tags): Post
    {
        return $this->container->make(SyncPostTagsAction::class)->execute($post, $tags);
    }

    /**
     * Publish every scheduled post whose time has come.
     *
     * @return int the number of posts published
     */
    public function publishDue(): int
    {
        return $this->container->make(PublishDuePostsAction::class)->execute();
    }

    /**
     * The post with this slug, as the configured `posts.model`: in `$locale` only when given,
     * else the current locale's match, then the fallback locale's, then any locale's.
     */
    public function findBySlug(string $slug, ?string $locale = null): ?Post
    {
        return PostModel::class()::findBySlug($slug, null, $locale);
    }

    /**
     * Published posts whose publish date has passed.
     *
     * @return Builder<Post>
     */
    public function published(): Builder
    {
        return $this->query()->published();
    }

    /**
     * A query on the configured `posts.model`.
     *
     * @return Builder<Post>
     */
    public function query(): Builder
    {
        return PostModel::query();
    }
}
