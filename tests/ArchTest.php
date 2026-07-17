<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Exceptions\PostsException;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Posts shipped **no architecture test at all**, so every preset here is a new guard rather
 * than a replacement — including the one that would have caught the fleet's 7×-shipped fatal
 * had it ever arrived in this package.
 */
ArchPresets::strictTypes('RoundlyConsulting\Posts');

/**
 * The deliberate extension points are exempt: `Post` is what `posts.model` invites a host to
 * subclass (pinned by the preset below instead), and PostsException is the base every posts
 * error extends so a host can catch them uniformly.
 *
 * Note the `$ignoring` PARAMETER rather than Pest's fluent `->ignoring()`. Only the parameter
 * is rot-checked (it registers `exemptionsExist` automatically): the fluent form accepts any
 * string and never verifies it, so a typo or an exemption that outlived its code is a silent
 * no-op and the ban then has a hole nobody can see.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Posts', [
    Post::class,
    PostsException::class,
]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal: `final` on a config-swappable model
 * is a PHP fatal the moment a host uses the seam the config documents. Post's docblock says
 * "Deliberately not `final`" — and until now that was a comment, enforced by nothing.
 *
 * It also pins the other direction: that `posts.model` really defaults to the packaged Post.
 */
ArchPresets::swappableModelsAreNotFinal([
    Post::class => 'posts.model',
]);

/**
 * Posts does no cryptography and has no reason to start. A standing guard.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Posts');

/**
 * Every `posts.model` read goes through Support\PostModel (which delegates to the toolkit's
 * ModelResolver). Adopted rather than rejected as jwt rejected it: posts has exactly the
 * shape the preset targets — a real Eloquent model behind a `*.model` key, resolved through a
 * Support seam.
 *
 * The key is declared rather than left to shape inference so the preset polices the one this
 * package means. In particular it must NOT be pointed at `posts.key_type` or
 * `posts.primary_key_type`: neither names a model. They are the two independent key axes —
 * `key_type` is the HOST's author model (outbound, somebody else's table),
 * `primary_key_type` is posts' own tables (inbound). They are not the same thing and must
 * never be merged.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support', ['posts.model']);

/**
 * The morph-key seam, guarded. Posts' author column migrated off raw `$table->morphs()` onto
 * `morphKey($name, KeyType::fromConfig('posts.key_type'))` so a uuid/ulid host keys the author
 * relation coherently — a hardcoded bigint id breaks those hosts on Postgres, and SQLite type
 * affinity hides it. This pin reds if a future migration reintroduces a raw morph and bypasses
 * the seam.
 */
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../database/migrations');

/**
 * The Dependency Policy as a test. No `alsoAllow`: posts' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If it goes red the graph is wrong —
 * never widen the allow-list to quiet it (bug #6 is a true positive).
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();
