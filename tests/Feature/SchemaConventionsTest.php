<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the schema the posts migration emits for EVERY supported key type.
 *
 * The author morph key is config-driven (`posts.key_type`), so the emitted DDL is
 * the package's real contract with a host's database. Each probe migrates the
 * source into a throwaway sqlite connection and reads the raw `sqlite_master` SQL,
 * so a change to the toolkit's Blueprint macros — or to the migration — cannot
 * silently move a column type.
 */
function probeSchema(string $keyType, bool $nullable = true): string
{
    $connection = 'schema_probe_'.$keyType.($nullable ? '_n' : '');

    Config::set('database.connections.'.$connection, [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    Config::set('posts.key_type', $keyType);
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

it('emits the full posts table on the shipped default', function (): void {
    $sql = probeSchema('bigint');

    foreach ([
        '"id" varchar not null',
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
        'primary key ("id")',
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
