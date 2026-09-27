# Changelog

All notable changes to `posts-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- A multilingual blog / posts engine: translatable title, slug, perex, content and SEO fields,
  with a polymorphic author via the `HasPosts` trait (bigint, UUID or ULID keys).
- A publishing lifecycle (`PostStatus`: draft, scheduled, published, archived) with
  `publish()`-style transitions, query scopes and the `posts:publish-scheduled` command.
- Nested categories and tags with translatable names and slugs, plus `inCategory()` and
  `withTag()` scopes.
- Per-post SEO meta with fallbacks — `setSeo()`, `renderMetaTags()` (title, description,
  canonical, Open Graph, Twitter, robots) and schema.org JSON-LD via `renderJsonLd()`.
- Per-locale slugs built on sluggable-for-laravel: unique per locale, locale-aware route binding,
  optional slug history with 301 redirects, a lock for published URLs and `Post::slugRules()`.
- `CreatePostAction`, `PublishPostAction` and `UpdatePostSeoAction`, and the `PostPublished`,
  `PostScheduled`, `PostArchived` and `PostDrafted` events.
- Featured image, gallery and inline `[media:UUID]` content tokens through media-library-for-laravel.
- Likes and reactions on posts through likes-for-laravel, with popular and trending feed scopes.
- Post reports and moderation through reports-for-laravel, with optional auto-unpublish when a
  report is upheld or a threshold is crossed.
