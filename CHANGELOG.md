# Changelog

All notable changes to `posts-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- A multilingual blog / posts engine: translatable title, slug, perex, content and SEO fields,
  with a polymorphic author via the `HasPosts` trait (bigint, UUID or ULID keys).
- A `Posts` facade (aliased `Posts`) over an injectable `PostsManager`: `Posts::create()`, a
  `Posts::draft()` builder (`title/slug/perex/content/metaTitle/metaDescription`, `by()`, `tags()`,
  `seo()`, then `save()` / `publish(?$at)` / `schedule($at)`), `publish()`, `schedule()`,
  `archive()`, `unpublish()`, `seo()`, `syncTags()`, `publishDue()`, `findBySlug()`,
  `published()` and `query()` — the last three always on the configured `posts.model`.
- `Posts::fake()`: a recording fake (a `PostsManager` subtype, so injected managers get it too)
  that sees every write — facade, builder, model methods, the moderation listener and the
  command — with `assertCreated/Published/Scheduled/Archived/Unpublished/SeoUpdated/Tagged/
  PublishedDue()` and an `assertNothing*()` twin for each.
- A publishing lifecycle (`PostStatus`: draft, scheduled, published, archived) with
  `publish()`, `schedule()`, `archive()` and `unpublish()` on the model (all routed through the
  manager), query scopes and the `posts:publish-scheduled` command (`Posts::publishDue()`).
- Nested categories and tags with translatable names and slugs, plus `inCategory()` and
  `withTag()` scopes.
- Per-post SEO meta with fallbacks — `setSeo()`, `renderMetaTags()` (title, description,
  canonical, Open Graph, Twitter, robots) and schema.org JSON-LD via `renderJsonLd()`.
- Per-locale slugs built on sluggable-for-laravel: unique per locale, locale-aware route binding,
  optional slug history with 301 redirects, a lock for published URLs and `Post::slugRules()`.
- One action per write — `CreatePostAction`, `PublishPostAction`, `SchedulePostAction`,
  `ArchivePostAction`, `UnpublishPostAction`, `UpdatePostSeoAction`, `SyncPostTagsAction` and
  `PublishDuePostsAction` — and the `PostPublished`, `PostScheduled`, `PostArchived` and
  `PostDrafted` events. A post created published is dated now when no date is given, a post
  created published or scheduled fires its event, a scheduled post needs a date, and the SEO
  DTO's meta title and description are kept on create.
- Featured image, gallery and inline `[media:UUID]` content tokens through media-library-for-laravel.
- Likes and reactions on posts through likes-for-laravel, with popular and trending feed scopes.
- Post reports and moderation through reports-for-laravel, with optional auto-unpublish when a
  report is upheld or a threshold is crossed.
