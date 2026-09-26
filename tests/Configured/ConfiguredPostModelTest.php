<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\MediaLibrary\Jobs\GenerateVariantsJob;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Listeners\WarmPostMediaVariants;
use RoundlyConsulting\Posts\Models\Category;
use RoundlyConsulting\Posts\Models\Tag;
use RoundlyConsulting\Posts\Support\PostModel;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;
use RoundlyConsulting\Posts\Tests\Support\CustomPost;

/**
 * The `posts.model` swap seam, driven end to end through a host subclass.
 *
 * Before this row, the seam was honoured in the two listeners and `HasPosts` but
 * BYPASSED in the route-model binding, the scheduled-publish command and both
 * taxonomy inverse relations — so a host that swapped the model still got the
 * packaged one wherever the package reached for a post itself.
 */
it('resolves the host model through the seam', function (): void {
    expect(PostModel::class())->toBe(CustomPost::class)
        ->and(PostModel::new())->toBeInstanceOf(CustomPost::class)
        ->and(PostModel::query()->getModel())->toBeInstanceOf(CustomPost::class);
});

it('binds routes to the host model', function (): void {
    Route::middleware(SubstituteBindings::class)
        ->get('/posts/{post:slug}', fn ($post) => $post::class);

    $post = CustomPost::factory()->withTitles(['en' => 'Host Bound'])->create();

    $this->get('/posts/host-bound')
        ->assertOk()
        ->assertSee(CustomPost::class);

    expect($post->refresh())->toBeInstanceOf(CustomPost::class);
});

it('publishes scheduled posts as the host model', function (): void {
    Event::fake([PostPublished::class]);
    CustomPost::$published = 0;

    CustomPost::factory()->scheduled()->create(['published_at' => now()->subMinute()]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    $post = CustomPost::query()->sole();

    // The row would look identical either way (same table), so the load-bearing
    // assertion is that the HOST model's own publish() ran.
    expect(CustomPost::$published)->toBe(1)
        ->and($post->status)->toBe(PostStatus::Published);

    Event::assertDispatched(PostPublished::class);
});

it('returns the host model from the taxonomy inverse relations', function (): void {
    $post = CustomPost::factory()->create();
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();

    $post->categories()->attach($category);
    $post->tags()->attach($tag);

    expect($category->posts()->first())->toBeInstanceOf(CustomPost::class)
        ->and($tag->posts()->first())->toBeInstanceOf(CustomPost::class);
});

it('authors host-model posts from a host model', function (): void {
    $author = AuthorTestModel::create(['name' => 'Ada']);

    CustomPost::factory()->forAuthor($author)->create();

    expect($author->posts()->first())->toBeInstanceOf(CustomPost::class)
        ->and($author->posts)->toHaveCount(1);
});

it('warms the media of a host-model post on publish', function (): void {
    Queue::fake();

    $post = CustomPost::factory()->create();

    (new WarmPostMediaVariants)->handle(new PostPublished((string) $post->getKey()));

    // No media attached, so nothing is queued — but the listener resolved the host
    // model rather than bailing out, which is what the seam has to guarantee.
    Queue::assertNotPushed(GenerateVariantsJob::class);

    expect(PostModel::query()->find($post->getKey()))->toBeInstanceOf(CustomPost::class);
});

it('finds host-model posts by slug through the seam', function (): void {
    // `Post::findBySlug()` would resolve through late static binding to the packaged class;
    // the seam's query is what returns the host's model.
    $post = CustomPost::factory()->withTitles(['en' => 'Seam Lookup'])->create();

    $found = PostModel::query()->whereSlug('seam-lookup')->first();

    expect($found)->toBeInstanceOf(CustomPost::class)
        ->and($found?->is($post))->toBeTrue()
        ->and(PostModel::query()->whereSlug('missing')->exists())->toBeFalse();
});
