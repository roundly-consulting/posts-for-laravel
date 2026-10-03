<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Models\Post;

it('reads a translatable attribute directly in the current locale', function (): void {
    app()->setLocale('sk');

    $post = Post::factory()->withTitles(['en' => 'Hello', 'sk' => 'Ahoj'])->create();

    expect($post->title)->toBe('Ahoj');

    app()->setLocale('en');

    expect($post->fresh()->title)->toBe('Hello');
});

it('falls back to a remaining translation when neither locale nor fallback match', function (): void {
    config()->set('translatable.fallback_locale', null);
    config()->set('posts.locales.fallback', null);
    config()->set('app.fallback_locale', null);

    $post = new Post;
    $post->setTranslation('title', 'de', 'Hallo');

    expect($post->getTranslation('title', 'fr'))->toBe('Hallo');
});

it('picks the same last-resort translation regardless of the stored key order', function (): void {
    config()->set('translatable.fallback_locale', null);
    config()->set('posts.locales.fallback', null);
    config()->set('app.fallback_locale', null);

    // The storage engine decides the key order of a json/jsonb column: sqlite keeps
    // insertion order, while Postgres jsonb and MySQL json normalise it. The last-resort
    // pick must not depend on it, so the same map in either order resolves identically.
    $insertionOrder = new Post;
    $insertionOrder->setRawAttributes(['title' => json_encode(['sk' => 'Ahoj', 'de' => 'Hallo'])]);

    $normalisedOrder = new Post;
    $normalisedOrder->setRawAttributes(['title' => json_encode(['de' => 'Hallo', 'sk' => 'Ahoj'])]);

    expect($insertionOrder->getTranslation('title', 'fr'))
        ->toBe($normalisedOrder->getTranslation('title', 'fr'));
});

it('resolves the last-resort translation by a stable locale sort, not storage order', function (): void {
    config()->set('translatable.fallback_locale', null);
    config()->set('posts.locales.fallback', null);
    config()->set('app.fallback_locale', null);

    $post = new Post;
    $post->setRawAttributes(['title' => json_encode(['sk' => 'Ahoj', 'de' => 'Hallo', 'at' => ''])]);

    // 'at' sorts first but is empty, so the lowest-sorting non-empty locale wins — never
    // the first key the engine happens to hand back.
    expect($post->getTranslation('title', 'fr'))->toBe('Hallo');
});

it('returns an empty string when an attribute has no translations at all', function (): void {
    $post = new Post;

    expect($post->getTranslation('title', 'en'))->toBe('')
        ->and($post->getTranslations('title'))->toBe([]);
});

it('resolves the fallback locale from the posts config when translatable config is absent', function (): void {
    config()->set('translatable.fallback_locale', null);
    config()->set('posts.locales.fallback', 'en');

    $post = new Post;
    $post->setTranslation('content', 'en', '<p>Body</p>');

    expect($post->getTranslation('content', 'sk'))->toBe('<p>Body</p>');
});

it('reads a blank translatable or posts fallback locale as not set (strict config)', function (): void {
    config()->set('translatable.fallback_locale', '');
    config()->set('posts.locales.fallback', ' ');
    config()->set('app.fallback_locale', 'de');

    $post = new Post;
    $post->setTranslation('title', 'de', 'Hallo');
    $post->setTranslation('title', 'at', 'Servus');

    // `at` sorts first, so only the app fallback (`de`) explains this pick.
    expect($post->getTranslation('title', 'fr'))->toBe('Hallo');
});

it('refuses a non-string translatable fallback locale (strict config)', function (): void {
    config()->set('translatable.fallback_locale', ['en']);

    $post = new Post;
    $post->setTranslation('title', 'en', 'Hello');

    expect(fn () => $post->getTranslation('title', 'sk'))->toThrow(
        RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException::class,
        'translatable.fallback_locale',
    );
});

it('translates an attribute via the translate helper for an explicit and the current locale', function (): void {
    app()->setLocale('en');

    $post = new Post;
    $post->setTranslation('title', 'en', 'Hello');
    $post->setTranslation('title', 'sk', 'Ahoj');

    expect($post->translate('title'))->toBe('Hello')
        ->and($post->translate('title', 'sk'))->toBe('Ahoj');
});

it('does not return a fallback translation when fallback is disabled', function (): void {
    $post = new Post;
    $post->setTranslation('title', 'en', 'Hello');

    expect($post->getTranslation('title', 'sk', false))->toBe('');
});

it('ignores non-string values stored in the raw translation map', function (): void {
    $post = new Post;
    $post->setRawAttributes(['title' => json_encode(['en' => 'Hello', 'sk' => 123])]);

    expect($post->getTranslations('title'))->toBe(['en' => 'Hello']);
});
