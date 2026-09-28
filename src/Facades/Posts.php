<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Facades;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\PostsManager;
use RoundlyConsulting\Posts\Support\PendingPost;
use RoundlyConsulting\Posts\Testing\PostsFake;

/**
 * @method static Post create(CreatePostData $data)
 * @method static PendingPost draft()
 * @method static Post publish(Post $post, ?CarbonInterface $at = null)
 * @method static Post schedule(Post $post, CarbonInterface $at)
 * @method static Post archive(Post $post)
 * @method static Post unpublish(Post $post)
 * @method static Post seo(Post $post, SeoData $data)
 * @method static Post syncTags(Post $post, iterable<int, string|Tag> $tags)
 * @method static int publishDue()
 * @method static Post|null findBySlug(string $slug, ?string $locale = null)
 * @method static Builder<Post> published()
 * @method static Builder<Post> query()
 * @method static PostsFake fake()
 * @method static void assertCreated(?Closure $callback = null)
 * @method static void assertNothingCreated()
 * @method static void assertPublished(Post $post, ?CarbonInterface $at = null)
 * @method static void assertNothingPublished()
 * @method static void assertScheduled(Post $post, ?CarbonInterface $at = null)
 * @method static void assertNothingScheduled()
 * @method static void assertArchived(Post $post)
 * @method static void assertNothingArchived()
 * @method static void assertUnpublished(Post $post)
 * @method static void assertNothingUnpublished()
 * @method static void assertSeoUpdated(Post $post, ?Closure $callback = null)
 * @method static void assertNothingSeoUpdated()
 * @method static void assertTagged(Post $post, list<string>|null $tags = null)
 * @method static void assertNothingTagged()
 * @method static void assertPublishedDue()
 * @method static void assertNothingPublishedDue()
 *
 * @see PostsManager
 * @see PostsFake
 */
final class Posts extends Facade
{
    /**
     * Swap in a recording fake behind the facade and the container. Every write — through the
     * facade, an injected manager, the `draft()` builder, the model's lifecycle methods and
     * `syncTags()`, the moderation listener or `posts:publish-scheduled` — is recorded instead
     * of run.
     */
    public static function fake(): PostsFake
    {
        $fake = app(PostsFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return PostsManager::class;
    }
}
