<?php

declare(strict_types=1);

/**
 * C — the config contract, pinned in both directions.
 *
 * This file replaces ~160 lines of hand-rolled reinvention: the suite carried its own
 * `scrapePostsKeys()` tokenizer, its own `shippedPostsKeys()` flattener, its own forward and
 * reverse cases, its own "bites on a shipped key nothing reads" self-test, and its own
 * stray-literal seam check. The ideas were right — it tokenized rather than regexed (the trap
 * media #27 fell into) and it even wrote its own bite proof — and that is exactly why they
 * should be the shared implementation rather than this package's copy of them.
 *
 * What the local version could not do, and the preset does:
 *  - it counted only `'posts.…'` string literals, so an injected `Repository::get()` or a
 *    `Config::get()` read was invisible to it;
 *  - it silently ignored interpolated keys instead of flagging them as uncheckable — an
 *    unresolvable key makes the read-set unsound, so reverse findings computed from it would
 *    be invented dead keys;
 *  - its prefix matching treated a read of `posts.seo` as covering every `posts.seo.*` leaf,
 *    so a dead leaf under a read parent was invisible;
 *  - `allowUnread`/`allowUnshipped` are rot-proof here — a stale entry that silences nothing
 *    is itself a failure. A hand-rolled skip list rots quietly.
 *  - the reverse direction now prints the directories it searched and states that a key read
 *    elsewhere is NOT proven dead, so a scope gap cannot be mistaken for a dead key and
 *    invite a destructive "fix".
 *
 * The `it('bites on a shipped key nothing reads')` self-test is deliberately not carried over:
 * it re-implemented the check against a locally-constructed array rather than exercising the
 * real one, so it proved its own copy of the logic. The reverse direction is bite-proved
 * against the shipped config instead, in the row's commit.
 *
 * The stray-literal seam check is not carried over either — `ArchPresets::modelsResolveThroughSeam`
 * in tests/ArchTest.php does that AND bans the late-static-binding half (`static::query()` /
 * `new static` resolving the *called* class rather than the configured one — permissions #34),
 * which the local version never checked.
 *
 * The bugs both directions exist for:
 *  - forward — shops #18: the whole store-credit feature read `shops.payments.*` while the
 *    file shipped `payment.*`; 330 tests stayed green because the suite set the same wrong key.
 *  - reverse — this package's own: `posts.seo.site-name` shipped an og:site_name that was
 *    never rendered, and two dead `posts.locales.*` keys survived.
 *
 * `resources/` is deliberately NOT passed as a third srcDir. It was tried, and removing it
 * again changed nothing: the two Blade views (`meta`, `json-ld`) read no config at all — they
 * are handed everything by the renderer. The scraper already auto-scans `routes/` and
 * `database/`, so the reasoning that widened it (the `git.webhooks.middleware` near-miss, where
 * a key read from `routes/` scraped as *dead* and I nearly had it deleted) is satisfied here
 * without an extra entry. An unproven srcDir is the same shape as an unproven
 * `extraReadPrefixes` entry: it is not rot-checked, so it can never announce that it silences
 * nothing. Narrower is better — if a view ever reads config, add it back and this note is the
 * reason to check.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/posts.php')->toSatisfyConfigContract(
        [__DIR__.'/../../src', __DIR__.'/../../database'],
        [
            // Three real reads that are not `config(` tokens, so the prefix is what makes them
            // visible to the scraper:
            //  - `posts.model` goes through the toolkit's `ModelResolver::for(…)` seam (via
            //    Support\PostModel);
            //  - `posts.key_type` and `posts.primary_key_type` go through `KeyType::fromConfig(…)`
            //    in the migrations, which is what decides the shipped column types.
            //
            // The last two are the package's two INDEPENDENT key axes and must never be
            // conflated: `key_type` describes the HOST's author model (outbound — somebody
            // else's table, which posts' `author` morph points at), `primary_key_type`
            // describes posts' OWN tables (inbound — what other packages' morph columns point
            // at). A single key cannot express both.
            'extraReadPrefixes' => ['posts.'],

            // Deliberately NO `excludeFromReverse` for the provider. The testing README's
            // example excludes the service provider on the grounds that "a render is not a
            // read" — but the toolkit's PackageServiceProvider both `contributesToAbout()`
            // and does real config reads in one file, so excluding it would discard the only
            // reader of several bound keys and weaken the reverse direction for nothing.
        ],
    );
});
