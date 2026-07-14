<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/**
 * The config contract, pinned in BOTH directions.
 *
 * - Forward: a key the code reads but the package never ships is unreachable —
 *   the host can never turn the feature on.
 * - Reverse: a key the package ships but no code reads is a documented feature
 *   that silently does nothing. That is how `posts.seo.site-name` shipped an
 *   og:site_name that was never rendered, and how the two dead `posts.locales.*`
 *   keys survived.
 *
 * Keys are scraped from real **string tokens**, never the file text — a docblock
 * mentioning a key is NOT a read, and a regex over raw text lets a dead key pass
 * vacuously.
 */

/** @return list<string> every `posts.*` string literal in a PHP source tree */
function scrapePostsKeys(string $directory): array
{
    $keys = [];

    /** @var iterable<SplFileInfo> $files */
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
            if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $literal = trim($token[1], "'\"");

            if (preg_match('/^posts\.[a-z0-9_.\-]+$/i', $literal) === 1) {
                $keys[$literal] = true;
            }
        }
    }

    $found = array_keys($keys);
    sort($found);

    return $found;
}

/** @return list<string> every dotted leaf path in the shipped config file */
function shippedPostsKeys(): array
{
    $config = require __DIR__.'/../../config/posts.php';

    $leaves = array_keys(Arr::dot($config));
    $paths = [];

    foreach ($leaves as $leaf) {
        // `tables.posts` etc — and every intermediate section, so a code read of a
        // whole section (e.g. `posts.tables`) is also considered shipped.
        $segments = explode('.', (string) $leaf);
        $path = '';

        foreach ($segments as $segment) {
            $path = $path === '' ? $segment : $path.'.'.$segment;
            $paths['posts.'.$path] = true;
        }
    }

    $shipped = array_keys($paths);
    sort($shipped);

    return $shipped;
}

it('reads no config key the package does not ship', function (): void {
    $read = scrapePostsKeys(__DIR__.'/../../src');
    $shipped = shippedPostsKeys();

    expect(array_values(array_diff($read, $shipped)))->toBe([]);
});

it('reads every config key the package ships', function (): void {
    $read = [
        ...scrapePostsKeys(__DIR__.'/../../src'),
        ...scrapePostsKeys(__DIR__.'/../../database'),
    ];

    // A leaf is "read" when the code names it, or names a section that contains it
    // (e.g. `config('posts.tables')` covers `posts.tables.posts`).
    $unread = [];

    foreach (shippedPostsKeys() as $shipped) {
        $covered = false;

        foreach ($read as $key) {
            if ($key === $shipped || str_starts_with($shipped, $key.'.') || str_starts_with($key, $shipped.'.')) {
                $covered = true;

                break;
            }
        }

        if (! $covered) {
            $unread[] = $shipped;
        }
    }

    expect($unread)->toBe([]);
});

it('bites on a shipped key nothing reads', function (): void {
    // Guard the guard: the reverse check above must actually reject a dead key.
    $shipped = [...shippedPostsKeys(), 'posts.totally.dead'];
    $read = scrapePostsKeys(__DIR__.'/../../src');

    $unread = array_values(array_filter(
        $shipped,
        function (string $key) use ($read): bool {
            foreach ($read as $found) {
                if ($key === $found || str_starts_with($key, $found.'.') || str_starts_with($found, $key.'.')) {
                    return false;
                }
            }

            return true;
        },
    ));

    expect($unread)->toContain('posts.totally.dead');
});

it('resolves the swappable model only through the Support seam', function (): void {
    // A model key honoured in some call sites and hard-coded in others is the
    // certificates/media bug. Nothing outside Support/ may name `posts.model`.
    /** @var iterable<SplFileInfo> $files */
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../src'));

    $offenders = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Support/')) {
            continue;
        }

        foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && trim($token[1], "'\"") === 'posts.model') {
                $offenders[] = $file->getFilename();
            }
        }
    }

    expect($offenders)->toBe([]);
});
