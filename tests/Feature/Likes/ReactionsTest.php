<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Contracts\Likeable;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Tests\Support\ReaderTestModel;

it('is a likeable subject', function (): void {
    expect(Post::factory()->create())->toBeInstanceOf(Likeable::class);
});

it('likes, unlikes and toggles a post with an explicit actor', function (): void {
    $post = Post::factory()->create();
    $user = ReaderTestModel::create(['name' => 'Ada']);

    Likes::actor($user)->like($post);

    expect($post->likesCount())->toBe(1)
        ->and($post->isLikedBy($user))->toBeTrue();

    Likes::actor($user)->unlike($post);

    expect($post->likesCount())->toBe(0)
        ->and($post->isLikedBy($user))->toBeFalse();

    expect(Likes::actor($user)->toggle($post))->toBeTrue()
        ->and($post->fresh()->isLikedBy($user))->toBeTrue()
        ->and(Likes::actor($user)->toggle($post))->toBeFalse();
});

it('hydrates like counts and orders a feed by popularity', function (): void {
    $popular = Post::factory()->create();
    $quiet = Post::factory()->create();

    $liker = ReaderTestModel::create(['name' => 'Ada']);
    $other = ReaderTestModel::create(['name' => 'Bo']);

    Likes::actor($liker)->like($popular);
    Likes::actor($other)->like($popular);
    Likes::actor($liker)->like($quiet);

    $hydrated = Post::query()->withLikesCount()->whereKey($popular->getKey())->first();

    expect((int) $hydrated->likes_count)->toBe($popular->likesCount())->toBe(2);

    $ordered = Post::query()->orderByLikesDesc()->get();

    expect($ordered->first()->is($popular))->toBeTrue()
        ->and($ordered->last()->is($quiet))->toBeTrue();
});

it('hydrates per-viewer liked state in a single feed query', function (): void {
    $liked = Post::factory()->create();
    $unliked = Post::factory()->create();

    $viewer = ReaderTestModel::create(['name' => 'Ada']);

    Likes::actor($viewer)->like($liked);

    $feed = Post::query()
        ->withLikedState($viewer)
        ->orderBy('id')
        ->get()
        ->keyBy(fn (Post $post): string => $post->getKey());

    expect((int) $feed[$liked->getKey()]->is_liked)->toBe(1)
        ->and($feed[$liked->getKey()]->liked_reaction)->toBe('like')
        ->and((int) $feed[$unliked->getKey()]->is_liked)->toBe(0)
        ->and($feed[$unliked->getKey()]->liked_reaction)->toBeNull();
});

it('renders a guest liked state without an actor', function (): void {
    $post = Post::factory()->create();

    $row = Post::query()->withLikedState()->whereKey($post->getKey())->first();

    expect((int) $row->is_liked)->toBe(0)
        ->and($row->liked_reaction)->toBeNull();
});

it('builds a compact like payload for a viewer', function (): void {
    $post = Post::factory()->create();
    $viewer = ReaderTestModel::create(['name' => 'Ada']);
    $other = ReaderTestModel::create(['name' => 'Bo']);

    Likes::actor($viewer)->like($post);
    Likes::actor($other)->like($post);

    $state = $post->likeState($viewer);

    expect($state['count'])->toBe(2)
        ->and($state['viewer_state']['liked'])->toBeTrue()
        ->and($state['viewer_state']['reaction'])->toBe('like')
        ->and($state['breakdown'])->toBe(['like' => 2]);
});

it('renders a guest like payload without a viewer', function (): void {
    $post = Post::factory()->create();
    $liker = ReaderTestModel::create(['name' => 'Ada']);

    Likes::actor($liker)->like($post);

    $state = $post->likeState();

    expect($state['count'])->toBe(1)
        ->and($state['viewer_state']['liked'])->toBeFalse()
        ->and($state['viewer_state']['reaction'])->toBeNull();
});
