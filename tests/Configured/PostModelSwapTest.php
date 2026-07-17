<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Actions\CreatePostAction;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostTranslationData;
use RoundlyConsulting\Posts\Support\PostModel;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;
use RoundlyConsulting\Posts\Tests\Support\CustomPost;

/**
 * S — the model-swap proof for `posts.model`, driven through the real creation flow from the
 * state a host actually boots in: ConfiguredPostTestCase sets the key BEFORE the providers
 * boot, and this directory is bound to it (Pest binds a test case per directory, not per file).
 * That matters here because the provider wires its route-model binding and its event listeners
 * against the configured class at boot — a swap applied afterwards would mask a seam that is
 * only honoured at runtime.
 *
 * ConfiguredPostModelTest beside it is kept and is already strong: it drives the route binding,
 * the scheduled-publish command, both taxonomy inverse relations and the media listener, and it
 * pins the load-bearing fact with a `$published` counter — because a bypassed seam reads and
 * writes the SAME table, so the row looks identical either way and only the host model's own
 * behaviour running can tell them apart. It found a real bypass in four call sites.
 *
 * What it structurally cannot do is what this adds: `$published` proves the host's *override*
 * ran on a post that already existed; `CountsCreations` proves the row was *created as* the
 * host's class in the first place. A flow that created the row as the packaged Post and
 * re-hydrated it as CustomPost passes `instanceof` and fires none of the host's model events.
 */
it('honours a host post model through the creation flow', function (): void {
    expect('posts.model')->toHonourModelSwap(CustomPost::class, function (): array {
        $author = AuthorTestModel::query()->create(['name' => 'Ada']);

        // The flow a host actually calls, not a raw create(): title/content are translatable.
        $post = app(CreatePostAction::class)->execute(new CreatePostData(
            translations: [
                new CreatePostTranslationData(locale: 'en', title: 'Hello', content: '<p>Hi</p>'),
            ],
            authorType: $author->getMorphClass(),
            authorId: $author->getKey(),
        ));

        return [
            $post,
            // Reads hydrate through the seam too, not just the writes.
            ...PostModel::query()->get()->all(),
            ...$author->posts()->get()->all(),
        ];
    });
});

// The structural half of the seam — `Post` is non-final (its docblock says "Deliberately not
// `final`", which until this row was enforced by nothing), and `posts.model` really defaults to
// the packaged Post — is pinned once in tests/ArchTest.php by
// `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does NOT live here: that preset
// asserts the config *default*, which this directory has swapped away.
