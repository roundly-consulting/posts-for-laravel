<?php

declare(strict_types=1);

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Listeners\WarmPostMediaVariants;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\PostsServiceProvider;

/**
 * `(bool) config()` read every non-empty string but "0" as on, so a host that fed a switch
 * `off` — or a typo — from `.env` got the feature switched ON. Every switch now goes through
 * the toolkit's strict reader: the boolean words work, anything else fails loudly.
 */
afterEach(function (): void {
    Env::getRepository()->clear('POSTS_SLUG_HISTORY');
});

it('hands the slug-history switch to the reader raw (strict config)', function (): void {
    Env::getRepository()->set('POSTS_SLUG_HISTORY', 'disabled');

    $config = require __DIR__.'/../../config/posts.php';

    expect($config['slugs']['history'])->toBe('disabled');
});

it('throws on a switch typo instead of reading it as the default (strict config)', function (string $key, Closure $read): void {
    config()->set($key, 'disabled');

    expect($read)->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [{$key}] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.",
    );
})->with([
    'slugs.unique' => ['posts.slugs.unique', fn () => Post::factory()->create()],
    'slugs.history' => ['posts.slugs.history', fn () => Post::factory()->create()],
    'slugs.route-binding (boot)' => ['posts.slugs.route-binding', function (): void {
        $provider = new PostsServiceProvider(app());
        $provider->register();
        $provider->boot();
    }],
    'media.inline.enabled' => ['posts.media.inline.enabled', fn () => Post::factory()->create()->renderContent()],
    'media.seo_og_image' => ['posts.media.seo_og_image', fn () => Post::factory()->create()->seo()],
    'media.warm_on_publish' => ['posts.media.warm_on_publish', fn () => (new WarmPostMediaVariants)->handle(new PostPublished(Post::factory()->create()->id))],
]);

it('refuses to render the about section over a switch typo (strict config)', function (string $key): void {
    config()->set($key, 'disabled');

    expect(fn () => Artisan::call('about', ['--only' => 'posts']))
        ->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'posts.author.nullable',
    'posts.slugs.route-binding',
    'posts.slugs.unique',
    'posts.slugs.history',
    'posts.slugs.lock-when-published',
    'posts.media.inline.enabled',
    'posts.media.warm_on_publish',
    'posts.media.seo_og_image',
    'posts.moderation.auto_unpublish',
]);

it('reads the boolean words strictly in the about section', function (): void {
    config()->set('posts.slugs.history', 'on');
    config()->set('posts.media.warm_on_publish', 'off');

    Artisan::call('about', ['--only' => 'posts']);

    expect(Artisan::output())
        ->toMatch('/Slug history\W+ON\b/')
        ->toMatch('/Warm on publish\W+OFF\b/');
});
