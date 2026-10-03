<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts;

use Closure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Posts\Commands\PublishScheduledPostsCommand;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Listeners\SyncPostVisibilityFromReports;
use RoundlyConsulting\Posts\Listeners\WarmPostMediaVariants;
use RoundlyConsulting\Posts\Support\ContentMediaRenderer;
use RoundlyConsulting\Posts\Support\PostModel;
use RoundlyConsulting\Posts\Support\PostsConfig;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;

final class PostsServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('posts')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasViews()
            ->hasCommands([
                PublishScheduledPostsCommand::class,
            ])
            ->contributesToAbout(fn (): array => $this->aboutPosts());
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ContentMediaRenderer::class);
        $this->app->singleton(PostsManager::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The migrations key every id column off the toolkit's `morphKey`/`ownerKey` macros,
        // so they must exist before the migrations run. This package registered neither and
        // worked only because `reports` — a dependency that happens to register them — booted
        // first: an accidental coupling that would have become a fatal the day posts stopped
        // requiring reports. Registration is idempotent, guarded by `hasMacro()`.
        $this->registerBlueprintMacros();

        if (Config::boolean('posts.slugs.route-binding', true)) {
            Route::model('post', PostModel::class());
        }

        Event::listen(PostPublished::class, WarmPostMediaVariants::class);

        // Moderation → visibility: upheld reports / threshold crossings auto-unpublish a post.
        Event::listen(ReportResolved::class, [SyncPostVisibilityFromReports::class, 'handleResolved']);
        Event::listen(ReportThresholdReached::class, [SyncPostVisibilityFromReports::class, 'handleThresholdReached']);
    }

    /**
     * The `php artisan about` payload.
     *
     * A blog's config names the host: its table names, its media disk, its
     * publisher and its site. So nothing renders a configured value — only the
     * model's base name, switches, counts, and SET/DEFAULT presence.
     *
     * @return array<string, string>
     */
    private function aboutPosts(): array
    {
        return [
            'Model' => class_basename(PostModel::class()),
            // Two axes, two lines — the ambiguity of a single "Key type" is what let a uuid
            // posts.id hide behind a bigint author key that was correct all along.
            'Key type (author)' => KeyType::fromConfig('posts.key_type')->value,
            'Key type (posts id)' => KeyType::fromConfig('posts.primary_key_type')->value,
            'Author' => Config::boolean('posts.author.nullable', true) ? 'OPTIONAL' : 'REQUIRED',
            'Tables' => $this->tables(),
            'Route binding' => $this->switch('posts.slugs.route-binding'),
            'Unique slugs' => $this->switch('posts.slugs.unique'),
            'Slug history' => Config::boolean('posts.slugs.history') ? 'ON' : 'OFF',
            'Slug lock' => Config::boolean('posts.slugs.lock-when-published') ? 'WHEN PUBLISHED' : 'OFF',
            'Media disk' => $this->orInvalid(static fn (): string => PostsConfig::mediaDisk() === null ? 'MEDIA DEFAULT' : 'SET'),
            'Inline media' => Config::boolean('posts.media.inline.enabled', true)
                ? $this->orInvalid(static fn (): string => 'ON (missing: '.PostsConfig::inlineOnMissing().')')
                : 'OFF',
            'Warm on publish' => $this->switch('posts.media.warm_on_publish'),
            'SEO' => 'og:image '.$this->switch('posts.media.seo_og_image')
                .', site name '.$this->orInvalid(static fn (): string => PostsConfig::siteName() === null ? 'MISSING' : 'SET'),
            'JSON-LD' => $this->orInvalid(static fn (): string => PostsConfig::jsonLdType()
                .' (publisher '.(PostsConfig::publisherName() === null ? 'MISSING' : 'SET').')'),
            'Moderation' => $this->moderation(),
        ];
    }

    private function tables(): string
    {
        $tables = config('posts.tables');
        $count = is_array($tables) ? count($tables) : 0;

        return $count.' table(s), '.($this->tablesAreDefault() ? 'DEFAULT' : 'CUSTOMISED');
    }

    /**
     * Every read is a literal key — a dynamically-built `config('posts.tables.'.$k)`
     * is invisible to the config-contract scrape, which is what keeps a shipped-but-
     * unread key from hiding.
     */
    private function tablesAreDefault(): bool
    {
        return config('posts.tables.posts') === 'posts'
            && config('posts.tables.categories') === 'post_categories'
            && config('posts.tables.category_post') === 'category_post'
            && config('posts.tables.tags') === 'post_tags'
            && config('posts.tables.tag_post') === 'post_tag';
    }

    private function moderation(): string
    {
        $autoUnpublish = $this->switch('posts.moderation.auto_unpublish');

        return $this->orInvalid(static fn (): string => 'on resolved: '.(PostsConfig::onResolved() ?? 'OFF')
            .', auto-unpublish: '.$autoUnpublish);
    }

    /**
     * A strict non-boolean read rendered for `about`, or `INVALID` when the setting is broken,
     * while every real read throws. (A switch typo still throws here too — see `switch()`.)
     *
     * @param  Closure(): string  $read
     */
    private function orInvalid(Closure $read): string
    {
        try {
            return $read();
        } catch (InvalidConfigurationException) {
            return 'INVALID';
        }
    }

    private function switch(string $key): string
    {
        return Config::boolean($key, true) ? 'ON' : 'OFF';
    }
}
