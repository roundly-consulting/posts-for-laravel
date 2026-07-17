<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\Posts\Commands\PublishScheduledPostsCommand;
use RoundlyConsulting\Posts\Events\PostPublished;
use RoundlyConsulting\Posts\Listeners\SyncPostVisibilityFromReports;
use RoundlyConsulting\Posts\Listeners\WarmPostMediaVariants;
use RoundlyConsulting\Posts\Support\ContentMediaRenderer;
use RoundlyConsulting\Posts\Support\PostModel;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;

final class PostsServiceProvider extends PackageServiceProvider
{
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
    }

    public function boot(): void
    {
        parent::boot();

        if ((bool) config('posts.slugs.route-binding', true)) {
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
            'Author' => (bool) config('posts.author.nullable', true) ? 'OPTIONAL' : 'REQUIRED',
            'Tables' => $this->tables(),
            'Route binding' => $this->switch('posts.slugs.route-binding'),
            'Unique slugs' => $this->switch('posts.slugs.unique'),
            'Media disk' => $this->presence('posts.media.disk', 'MEDIA DEFAULT'),
            'Inline media' => (bool) config('posts.media.inline.enabled', true)
                ? 'ON (missing: '.$this->onMissing().')'
                : 'OFF',
            'Warm on publish' => $this->switch('posts.media.warm_on_publish'),
            'SEO' => 'og:image '.$this->switch('posts.media.seo_og_image')
                .', site name '.$this->presence('posts.seo.site-name', 'MISSING'),
            'JSON-LD' => (string) config('posts.json-ld.type', 'BlogPosting')
                .' (publisher '.$this->presence('posts.json-ld.publisher.name', 'MISSING').')',
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
        $onResolved = config('posts.moderation.on_resolved');
        $resolved = is_string($onResolved) && $onResolved !== '' ? $onResolved : 'OFF';

        return 'on resolved: '.$resolved.', auto-unpublish: '.$this->switch('posts.moderation.auto_unpublish');
    }

    private function onMissing(): string
    {
        $strategy = config('posts.media.inline.on_missing', 'strip');

        return $strategy === 'keep' ? 'keep' : 'strip';
    }

    private function switch(string $key): string
    {
        return (bool) config($key, true) ? 'ON' : 'OFF';
    }

    private function presence(string $key, string $absent): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? 'SET' : $absent;
    }
}
