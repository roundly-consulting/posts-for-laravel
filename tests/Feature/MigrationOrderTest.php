<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RoundlyConsulting\PackageToolkit\Support\MigrationPublisher;

/**
 * Migrations are publish-only, so **directory sort order is run order** in a host.
 * A package whose ALTER sorts before its CREATE — or whose foreign key points at a
 * table that has not been created yet — is unrunnable from a clean database, and a
 * sqlite-only suite CANNOT prove otherwise (SQLite silently accepts a CREATE TABLE
 * referencing a missing parent; MySQL/Postgres reject it at DDL time).
 *
 * So the load-bearing assertion is the STRUCTURAL, engine-independent one: read
 * every foreign key out of the sources — in all three of Laravel's forms — and
 * assert the parent's CREATE sorts first, and that every ALTER follows the CREATE
 * of the table it touches.
 *
 * posts today declares **zero** foreign keys and **zero** ALTERs (all five sources
 * are CREATEs), so the checker has nothing to reject. That is exactly how a guard
 * passes vacuously — so the analyser is ALSO run against synthetic broken sources
 * below, proving it bites before it is trusted on the real ones.
 */

/** @return list<string> */
function migrationSources(): array
{
    $files = glob(__DIR__.'/../../database/migrations/*.php') ?: [];
    sort($files);

    return array_values($files);
}

/** The table a source CREATEs (resolving a `Schema::create(config(...))` variable). */
function createdTable(string $body): ?string
{
    if (preg_match("/Schema::create\(.*?config\('([a-z0-9_.]+)',\s*'([a-z0-9_]+)'/i", $body, $m) === 1) {
        $configured = config($m[1]);

        return is_string($configured) ? $configured : $m[2];
    }

    if (preg_match("/Schema::create\('([a-z0-9_]+)'/i", $body, $m) === 1) {
        return $m[1];
    }

    return null;
}

/** The table a source ALTERs. */
function alteredTable(string $body): ?string
{
    if (preg_match("/Schema::table\(.*?config\('([a-z0-9_.]+)',\s*'([a-z0-9_]+)'/i", $body, $m) === 1) {
        $configured = config($m[1]);

        return is_string($configured) ? $configured : $m[2];
    }

    if (preg_match("/Schema::table\('([a-z0-9_]+)'/i", $body, $m) === 1) {
        return $m[1];
    }

    return null;
}

/**
 * The parent tables of every foreign key in a source, in all three of Laravel's
 * forms: `->constrained('parent')`, a bare `foreignId('x_id')->constrained()`
 * (parent derived from the column name), and the long-hand
 * `->references('id')->on('parent')`.
 *
 * @return list<string>
 */
function foreignKeyParents(string $body): array
{
    $parents = [];

    preg_match_all("/->constrained\(\s*'([a-z0-9_]+)'\s*\)/i", $body, $named);
    $parents = [...$parents, ...$named[1]];

    preg_match_all("/foreignId(?:For)?\(\s*'([a-z0-9_]+)'\s*\)(?:(?!->constrained)[^;])*->constrained\(\s*\)/is", $body, $bare);
    foreach ($bare[1] as $column) {
        $parents[] = Str::plural(Str::beforeLast($column, '_id'));
    }

    preg_match_all("/->references\('[a-z0-9_]+'\)\s*->on\('([a-z0-9_]+)'\)/i", $body, $longhand);
    $parents = [...$parents, ...$longhand[1]];

    return array_values($parents);
}

/**
 * Every ordering violation in a list of migration bodies, in their run order.
 *
 * @param  list<string>  $bodies  keyed by their position in the run order
 * @return list<string>
 */
function orderViolations(array $bodies): array
{
    $createdAt = [];

    foreach ($bodies as $index => $body) {
        $table = createdTable($body);

        if ($table !== null) {
            $createdAt[$table] = $index;
        }
    }

    $violations = [];

    foreach ($bodies as $index => $body) {
        $self = createdTable($body);

        foreach (foreignKeyParents($body) as $parent) {
            if (! array_key_exists($parent, $createdAt)) {
                $violations[] = "no migration creates [{$parent}]";

                continue;
            }

            // A self-referencing key sorts WITH its own file, not before it.
            $bound = $parent === $self ? $index : $index - 1;

            if ($createdAt[$parent] > $bound) {
                $violations[] = "migration #{$index} references [{$parent}] before it exists";
            }
        }

        $altered = alteredTable($body);

        if ($altered === null) {
            continue;
        }

        if (! array_key_exists($altered, $createdAt)) {
            $violations[] = "no migration creates [{$altered}]";

            continue;
        }

        if ($createdAt[$altered] >= $index) {
            $violations[] = "migration #{$index} alters [{$altered}] before it exists";
        }
    }

    return $violations;
}

it('parses every foreign key form and rejects a broken order (the guard-the-guard check)', function (): void {
    // `->constrained('parent')`, sorted AFTER its parent — legal.
    $named = <<<'PHP'
    Schema::create('children', function (Blueprint $table): void {
        $table->foreignId('parent_id')->constrained('parents');
    });
    PHP;

    // A bare `->constrained()`, deriving `parents` from the column name.
    $bare = <<<'PHP'
    Schema::create('bare_children', function (Blueprint $table): void {
        $table->foreignId('parent_id')->nullable()->index()->constrained();
    });
    PHP;

    // The long-hand form.
    $longhand = <<<'PHP'
    Schema::create('longhand_children', function (Blueprint $table): void {
        $table->unsignedBigInteger('parent_id');
        $table->foreign('parent_id')->references('id')->on('parents');
    });
    PHP;

    $parents = "Schema::create('parents', function (Blueprint \$table): void { \$table->id(); });";
    $alter = "Schema::table('parents', function (Blueprint \$table): void { \$table->string('x'); });";

    expect(foreignKeyParents($named))->toBe(['parents'])
        ->and(foreignKeyParents($bare))->toBe(['parents'])
        ->and(foreignKeyParents($longhand))->toBe(['parents']);

    // Correct order: no violations for any of the three forms.
    expect(orderViolations([$parents, $named, $bare, $longhand, $alter]))->toBe([]);

    // Each form, sorted BEFORE its parent, is caught.
    expect(orderViolations([$named, $parents]))->not->toBe([]);
    expect(orderViolations([$bare, $parents]))->not->toBe([]);
    expect(orderViolations([$longhand, $parents]))->not->toBe([]);

    // An ALTER before its CREATE is caught (the approvals bug).
    expect(orderViolations([$alter, $parents]))->not->toBe([]);
});

it('ships migrations that run in directory order from an empty database', function (): void {
    $bodies = array_map(
        static fn (string $file): string => (string) file_get_contents($file),
        migrationSources(),
    );

    expect(orderViolations($bodies))->toBe([]);
});

it('declares no foreign key and no ALTER at all', function (): void {
    // The fact that makes the order above trivially safe — pinned, so that a future
    // migration adding an FK or an ALTER cannot land without revisiting the order.
    $sources = migrationSources();

    expect($sources)->toHaveCount(5);

    foreach ($sources as $source) {
        $body = (string) file_get_contents($source);

        expect(foreignKeyParents($body))->toBe([], basename($source))
            ->and(alteredTable($body))->toBeNull(basename($source))
            ->and(createdTable($body))->not->toBeNull(basename($source));
    }
});

it('publishes the migrations in the directory sort order', function (): void {
    $timestamp = now();
    $destinations = [];

    foreach (migrationSources() as $offset => $source) {
        $destinations[] = MigrationPublisher::destination(
            MigrationPublisher::nameFor($source),
            database_path('migrations'),
            $timestamp->copy()->addSeconds($offset),
        );
    }

    $sorted = $destinations;
    sort($sorted);

    expect($destinations)->toBe($sorted)
        ->and($destinations)->toHaveCount(5);
});

it('migrates the published filenames into a fresh empty database', function (): void {
    $directory = sys_get_temp_dir().'/posts-migration-order-'.Str::random(8);
    File::ensureDirectoryExists($directory);

    $timestamp = now();

    foreach (migrationSources() as $offset => $source) {
        $name = MigrationPublisher::nameFor($source);
        $stamp = $timestamp->copy()->addSeconds($offset)->format('Y_m_d_His');

        File::copy($source, $directory.'/'.$stamp.'_'.$name.'.php');
    }

    Config::set('database.connections.migration_order_probe', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    $previous = (string) config('database.default');
    Config::set('database.default', 'migration_order_probe');

    try {
        $this->artisan('migrate', [
            '--path' => $directory,
            '--realpath' => true,
            '--database' => 'migration_order_probe',
        ])->assertSuccessful();

        $tables = collect(DB::connection('migration_order_probe')
            ->select("select name from sqlite_master where type = 'table'"))
            ->pluck('name')
            ->all();

        expect($tables)
            ->toContain('posts')
            ->toContain('post_categories')
            ->toContain('post_tags')
            ->toContain('category_post')
            ->toContain('post_tag');
    } finally {
        Config::set('database.default', $previous);
        DB::purge('migration_order_probe');
        File::deleteDirectory($directory);
    }
});
