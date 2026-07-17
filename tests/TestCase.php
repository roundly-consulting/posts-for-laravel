<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Likes\LikesServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Posts\PostsServiceProvider;
use RoundlyConsulting\Reports\ReportsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider the suite really needs, in registration order — all four are hard
     * `require`s a host would auto-discover, and posts genuinely runs on each: media backs
     * the featured image and gallery buckets, likes backs reactions, reports backs
     * report-a-post, and approvals backs reports' multi-moderator flow.
     *
     * Reports is also what registers the toolkit's `morphKey` blueprint macros that this
     * package's own migrations call — a latent fatal that was already found and fixed. The
     * order here is load-bearing for that reason; do not "tidy" posts earlier.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [
            ApprovalsServiceProvider::class,
            LikesServiceProvider::class,
            MediaLibraryServiceProvider::class,
            ReportsServiceProvider::class,
            PostsServiceProvider::class,
        ];
    }

    /**
     * The migrations, named by **provider class** — never by filename.
     *
     * This replaces a hand-rolled `defineDatabaseMigrations()` that reflected on four
     * providers to find their package roots and then guessed `/database/migrations` beneath
     * each: exactly what the base case's `LoadsProviderMigrations` concern does once,
     * correctly, for the whole fleet.
     *
     * The three fixture author tables (`users`, `uuid_users`, `ulid_users`) were bare
     * `Schema::create()` calls here, i.e. outside the migrator and outside every reset. They
     * are fixture migrations now, so the base case's drop-and-remigrate reset owns them like
     * any other table.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            __DIR__.'/database/migrations',
            PostsServiceProvider::class,
            MediaLibraryServiceProvider::class,
            LikesServiceProvider::class,
            ReportsServiceProvider::class,
            ApprovalsServiceProvider::class,
        ];
    }

    /**
     * The two independent key axes, plus the media wiring — all applied BEFORE the providers
     * boot and before the migrations run, which is the only window that matters: the
     * migrations read the key types to pick column types, and the models read them to decide
     * whether to mint an id.
     *
     * This is `configBeforeBoot()` rather than the `defineEnvironment()` override it used to
     * be. PackageTestCase does its whole job in `defineEnvironment()` — `DriverMatrix::configure()`
     * + these keys + the model swaps — so an override without `parent::` decapitates the base
     * case silently: no error, no red, DriverMatrix simply never configured and the pgsql leg
     * quietly running sqlite. This package's whole reason for having a pgsql leg is that only
     * a strict engine can tell its key types apart, so that failure would be especially
     * expensive here.
     *
     * The hand-rolled `configureConnection()` is gone: `DriverMatrix::configure()` is the
     * fleet's version of the same seam, reading the same `TESTING_DB_*` vars, and it also
     * turns SQLite's foreign-key pragma on — which the local version never did.
     *
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return [
            'posts.key_type' => $this->authorKeyType(),
            'posts.primary_key_type' => $this->postsKeyType(),

            // Media-library: store on a fakeable public disk, use the GD driver, and keep the
            // responsive ladder small so variant generation stays fast under test.
            'media.disk' => 'public',
            'media.image_driver' => 'gd',
            'media.responsive.widths' => [320, 640],
        ];
    }

    /**
     * The key type of the host's AUTHOR models — the OUTBOUND axis, which the host owns and
     * posts points at through its `author` morph.
     *
     * Deliberately NOT the same thing as {@see postsKeyType()}, and the two must never be
     * merged: `posts.key_type` describes somebody else's table, `posts.primary_key_type`
     * describes ours.
     */
    protected function authorKeyType(): string
    {
        return 'bigint';
    }

    /**
     * The key type of posts' OWN tables — the INBOUND axis, which other packages' morph
     * columns point at.
     */
    protected function postsKeyType(): string
    {
        return 'bigint';
    }
}
