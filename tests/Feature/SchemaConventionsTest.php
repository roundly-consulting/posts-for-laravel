<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the schema the posts migration emits for EVERY supported key type, on BOTH axes.
 *
 * The two axes are independent and both config-driven, so the emitted DDL is the package's
 * real contract with a host's database:
 *
 *  - `posts.key_type` — OUTBOUND: the author morph key, i.e. the key type of the host's own
 *    author models. The host owns this.
 *  - `posts.primary_key_type` — INBOUND: `posts.id` itself, which other packages' morph
 *    columns point at. This one shipped as an unconditional `uuid` against a fleet of
 *    bigint morph columns, so a post could not be liked on PostgreSQL at all.
 *
 * Each probe migrates the source into a throwaway sqlite connection and reads the raw
 * `sqlite_master` SQL, so a change to the toolkit's Blueprint macros — or to the migration —
 * cannot silently move a column type.
 */
function probeSchema(string $keyType, bool $nullable = true, string $primaryKeyType = 'bigint'): string
{
    $connection = 'schema_probe_'.$keyType.($nullable ? '_n' : '').'_'.$primaryKeyType;

    Config::set('database.connections.'.$connection, [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    Config::set('posts.key_type', $keyType);
    Config::set('posts.primary_key_type', $primaryKeyType);
    Config::set('posts.author.nullable', $nullable);

    $previous = (string) config('database.default');
    Config::set('database.default', $connection);

    try {
        $migration = require __DIR__.'/../../database/migrations/create_posts_table.php';
        $migration->up();

        $row = DB::connection($connection)
            ->selectOne("select sql from sqlite_master where type = 'table' and name = 'posts'");

        return (string) ($row->sql ?? '');
    } finally {
        Config::set('database.default', $previous);
        DB::purge($connection);
    }
}

it('emits an unsignedBigInteger author key on the bigint default', function (): void {
    expect(probeSchema('bigint'))
        ->toContain('"author_type" varchar,')
        ->toContain('"author_id" integer,');
});

it('emits a uuid author key when configured for uuid', function (): void {
    expect(probeSchema('uuid'))
        ->toContain('"author_type" varchar,')
        ->toContain('"author_id" varchar,');
});

it('emits a ulid author key when configured for ulid', function (): void {
    expect(probeSchema('ulid'))
        ->toContain('"author_type" varchar,')
        ->toContain('"author_id" varchar,');
});

it('falls back to bigint for an unrecognized key type instead of throwing', function (): void {
    expect(probeSchema('bogus'))->toContain('"author_id" integer,');
});

it('accepts the legacy id alias for bigint', function (): void {
    expect(probeSchema('id'))->toContain('"author_id" integer,');
});

it('emits a required morph pair when the author is not nullable', function (): void {
    expect(probeSchema('bigint', nullable: false))
        ->toContain('"author_type" varchar not null')
        ->toContain('"author_id" integer not null');
});

/**
 * The inbound axis. `bigint` is the default because it is the only type a raw `morphs()`
 * column — what 22 of the fleet's packages emit — can hold on a strict engine.
 */
it('emits an auto-incrementing integer post id on the shipped default', function (): void {
    expect(probeSchema('bigint'))
        ->toContain('"id" integer primary key autoincrement not null');
});

it('emits a uuid post id when configured for uuid', function (): void {
    expect(probeSchema('bigint', primaryKeyType: 'uuid'))
        ->toContain('"id" varchar not null')
        ->toContain('primary key ("id")');
});

it('emits a ulid post id when configured for ulid', function (): void {
    expect(probeSchema('bigint', primaryKeyType: 'ulid'))
        ->toContain('"id" varchar not null')
        ->toContain('primary key ("id")');
});

it('falls back to a bigint post id for an unrecognized key type instead of throwing', function (): void {
    expect(probeSchema('bigint', primaryKeyType: 'bogus'))
        ->toContain('"id" integer primary key autoincrement not null');
});

/**
 * The axes are independent: a host with uuid authors and bigint posts is an ordinary
 * application, and a single `key_type` could never have expressed it. Pinning the crossed
 * combination is what stops the two keys being quietly re-merged.
 */
it('keeps the author key and the post id independent', function (): void {
    expect(probeSchema('uuid', primaryKeyType: 'bigint'))
        ->toContain('"id" integer primary key autoincrement not null')
        ->toContain('"author_id" varchar,');

    expect(probeSchema('bigint', primaryKeyType: 'uuid'))
        ->toContain('"id" varchar not null')
        ->toContain('"author_id" integer,');
});

it('emits the full posts table on the shipped default', function (): void {
    $sql = probeSchema('bigint');

    foreach ([
        '"id" integer primary key autoincrement not null',
        '"status" varchar not null default \'draft\'',
        '"published_at" datetime',
        '"title" text',
        '"slug" text',
        '"perex" text',
        '"content" text',
        '"meta_title" text',
        '"meta_description" text',
        '"seo" text',
        '"author_type" varchar,',
        '"author_id" integer,',
        '"created_at" datetime',
        '"updated_at" datetime',
        '"deleted_at" datetime',
    ] as $fragment) {
        expect($sql)->toContain($fragment);
    }
});

it('indexes the author morph pair', function (): void {
    $indexes = collect(Schema::getIndexes(config('posts.tables.posts')))
        ->pluck('columns')
        ->all();

    expect($indexes)->toContain(['author_type', 'author_id']);
});

it('names the morph columns after the configured morph name', function (): void {
    Config::set('posts.author.morph-name', 'writer');

    $sql = probeSchema('uuid');

    expect($sql)
        ->toContain('"writer_type" varchar')
        ->toContain('"writer_id" varchar')
        ->not->toContain('"author_id"');
});
