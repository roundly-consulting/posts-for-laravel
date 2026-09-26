<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Sluggable\Events\SlugCollisionRetried;

/**
 * The database half of slug uniqueness: the create migrations build one unique index per
 * supported locale on `slug`, so two writers racing past the application-level probe cannot
 * both commit the same slug. These run on every leg — SQLite here, Postgres on `test-pgsql`,
 * where the index is an expression index on `slug->>'en'`.
 */
dataset('slug tables', [
    'posts' => 'posts.tables.posts',
    'categories' => 'posts.tables.categories',
    'tags' => 'posts.tables.tags',
]);

it('builds a unique slug index per supported locale', function (string $key): void {
    $table = (string) config($key);

    $unique = collect(Schema::getIndexes($table))
        ->filter(fn (array $index): bool => (bool) $index['unique'])
        ->pluck('name')
        ->all();

    expect($unique)->toContain("{$table}_slug_en_slug_unique")
        ->and($unique)->toContain("{$table}_slug_sk_slug_unique");
})->with('slug tables');

it('rejects a duplicate slug written past the model', function (string $key): void {
    $table = (string) config($key);
    $row = ['slug' => json_encode(['en' => 'same-slug']), 'created_at' => now(), 'updated_at' => now()];

    DB::table($table)->insert($row);

    expect(fn () => DB::table($table)->insert($row))->toThrow(UniqueConstraintViolationException::class);
})->with('slug tables');

it('lets the same slug live in two locales', function (): void {
    DB::table((string) config('posts.tables.tags'))->insert([
        'slug' => json_encode(['en' => 'news', 'sk' => 'news']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table((string) config('posts.tables.tags'))->count())->toBe(1);
});

it('retries a slug that lost the race to a concurrent writer', function (): void {
    $retries = 0;
    Event::listen(SlugCollisionRetried::class, function () use (&$retries): void {
        $retries++;
    });

    // Another writer commits `hello-world` after this save probed the slug as free but
    // before its INSERT — the window only the index can close. Booting the model first puts
    // sluggable's own `creating` hook (the probe) ahead of this listener.
    new Post;
    $raced = false;
    Post::creating(function () use (&$raced): void {
        if ($raced) {
            return;
        }

        $raced = true;

        DB::table((string) config('posts.tables.posts'))->insert([
            'status' => 'draft',
            'slug' => json_encode(['en' => 'hello-world']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $post = Post::factory()->withTitles(['en' => 'Hello World'])->create();

    expect($post->getTranslation('slug', 'en'))->toBe('hello-world-2')
        ->and($retries)->toBe(1)
        ->and(Post::query()->count())->toBe(2);
});

it('still migrates on an engine sluggable cannot index', function (string $migration): void {
    // SQL Server has no slug-index form in sluggable; the create migrations must fall back to
    // application-level uniqueness there, not abort the install. The engine is never reached:
    // the table DDL is faked, and sluggable picks its index driver before it runs any SQL.
    $default = config('database.default');
    config()->set('database.connections.sqlsrv_probe', ['driver' => 'sqlsrv', 'host' => 'localhost', 'database' => 'posts', 'prefix' => '']);
    config()->set('database.default', 'sqlsrv_probe');
    Schema::shouldReceive('create')->once();

    try {
        (require __DIR__.'/../../database/migrations/'.$migration)->up();
        $thrown = null;
    } catch (Throwable $exception) {
        $thrown = $exception;
    } finally {
        // The base case's reset runs on the default connection after the test.
        config()->set('database.default', $default);
        Schema::clearResolvedInstances();
    }

    expect($thrown)->toBeNull();
})->with(['create_posts_table.php', 'create_post_categories_table.php', 'create_post_tags_table.php']);
