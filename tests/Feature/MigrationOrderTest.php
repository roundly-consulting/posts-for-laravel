<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Posts\Actions\CreatePostAction;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostData;
use RoundlyConsulting\Posts\DataTransferObjects\CreatePostTranslationData;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\PostsServiceProvider;
use RoundlyConsulting\Posts\Tests\Support\AuthorTestModel;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * P + R for the five post tables.
 *
 * This file replaces ~290 lines of hand-rolled reinvention: the suite carried its own
 * migration globber, its own `Schema::create(config(...))` resolver, its own three-form
 * FK-edge walker, its own ALTER placement check, its own synthetic-broken-source self-test
 * and its own publish-and-migrate case against a throwaway **SQLite** file — which is the one
 * engine that cannot fail an ordering check.
 *
 * Its docblock deserves credit and is the reason this row is straightforward: it had already
 * worked out that posts declares zero foreign keys and zero ALTERs, and that a guard with
 * nothing to reject passes vacuously — so it ran its analyser against synthetic broken
 * sources to prove it bit. That instinct is right, and it is now the shared implementation's
 * job rather than this package's copy of it.
 */
$migrations = __DIR__.'/../../database/migrations';

/**
 * M — `toHaveRunnableMigrationOrder` — is deliberately NOT adopted, and this note is the cause
 * rather than an omission.
 *
 * `MigrationGraph::assertRunnable()` checks two independent things and only one is about
 * foreign keys: it also pins that a `Schema::table()` ALTER sorts at or after the CREATE of
 * the table it alters (approvals #2). Posts ships **five CREATEs, zero FK edges and zero
 * ALTERs** — verified against the migration source, not the row spec — so *both* halves are
 * inert. There is no edge to order and no ALTER to place.
 *
 * The contrast is the clean illustration: `approvals` also has 0 FKs but ships 2 ALTERs, so it
 * adopts M with a `foreignKeys: 0` live pin; `connections` has 0 FKs and 0 ALTERs and rejects
 * it, as this row does. The criterion is FK edges OR ALTERs, not FK edges alone.
 *
 * Every relational column here is an `ownerKey()` or a `morphKey()` — deliberately
 * unconstrained, because the parent's key type is configurable (`posts.primary_key_type`) and
 * an author can live in any host table. If a real FK or an ALTER is ever added, this row must
 * adopt M rather than inherit this note.
 */

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages). `count: 5` pins the file count so neither check can pass over an empty or
 * relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(PostsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes every migration timestamp-injected into the host', function (): void {
    expect(PostsServiceProvider::class)->toPublishMigrationsTimestamped('posts-migrations', 5);
});

/**
 * R — the real-engine proof. The deleted local version ran the published files against a
 * throwaway SQLite database; this runs them against Postgres, which is the only engine that
 * can tell this package's key types apart at all (SQLite reports bigint and integer alike as
 * `integer` and stores uuid and ulid alike as `varchar`). `migrations: 5` pins the count, and
 * the expectation additionally fails a set that "applies cleanly" while creating no tables —
 * an empty `up()` otherwise passes and proves nothing.
 *
 * The negative control (`toRejectBrokenOrderOnConnection`) is deliberately NOT adopted: it
 * asserts the engine *refuses* a reordered set, and with zero foreign keys Postgres has
 * nothing to refuse, so it would fail loudly by design. That is the assertion working
 * correctly against a shape it does not fit, not a red to chase (credits, tested not assumed).
 */
it('applies its migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 5);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The driver-truth pin: compares the env-declared driver against what the connection itself
 * answers, so a leg that exports the location vars but not `TESTING_DB_DRIVER` (or a TestCase
 * that decapitates the base case by overriding `defineEnvironment()` without `parent::`) reds
 * instead of quietly running sqlite and reporting green as a "postgres" job. Strictly stronger
 * than reading a skip count by hand — and this package is the one where it matters most, since
 * its entire reason for a real engine is a distinction sqlite cannot make.
 */
it('runs on the driver the leg declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

/**
 * The `status` enum column and the jsonb translation/SEO columns are what the drivers render
 * differently — `json` has no equality operator on Postgres at all, and jsonb reorders object
 * keys. Pinning a round-trip on whatever engine the leg configured proves the columns are
 * usable rather than merely creatable, through the real creation flow rather than a raw
 * `create()` (title/content are translatable, so a raw string assignment is not what a host
 * writes).
 */
it('round-trips a multilingual post on the configured engine', function (): void {
    $author = AuthorTestModel::query()->create(['name' => 'Ada']);

    $post = app(CreatePostAction::class)->execute(new CreatePostData(
        translations: [
            new CreatePostTranslationData(locale: 'en', title: 'Structured', content: '<p>Hi</p>'),
            new CreatePostTranslationData(locale: 'sk', title: 'Struktura', content: '<p>Ahoj</p>'),
        ],
        status: PostStatus::Draft,
        authorType: $author->getMorphClass(),
        authorId: $author->getKey(),
    ));

    $fresh = $post->fresh();

    expect($fresh?->status)->toBe(PostStatus::Draft)
        ->and($fresh?->author_type)->toBe($author->getMorphClass())
        ->and($fresh?->author_id)->toBe($author->getKey())
        // Key-by-key rather than `toBe` on the whole translation map: jsonb sorts object keys
        // (by length, then bytewise), so an `['en' => …, 'sk' => …]` map comes back reordered
        // and a whole-map `toBe` (`===`, order-sensitive) would red on Postgres while passing
        // on sqlite.
        ->and($fresh?->getTranslation('title', 'en'))->toBe('Structured')
        ->and($fresh?->getTranslation('title', 'sk'))->toBe('Struktura');
});
