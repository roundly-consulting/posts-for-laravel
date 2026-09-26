# Changelog

All notable changes to `posts-for-laravel` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Changed

- Slugs for posts, categories and tags now run on `sluggable-for-laravel` (hard dependency):
  bounded collision probing that trashed and globally-scoped rows can't hide, per-locale unique
  indexes built by the create migrations, and retry on a racing insert.
- Route binding follows the locale chain current → fallback → any locale, and the slug is now the
  post's route key, so `route(…, $post)` emits the slug the binder resolves.
- Manual slugs (including `CreatePostAction`) are normalised and made unique.
- A no-op `save()` no longer back-fills missing slug locales; use `sluggable:regenerate --mode=missing`.

### Added

- `posts.slugs.history` (slug history + 301 redirects) and `posts.slugs.lock-when-published`.
- `Post::slugRules()` for host form requests.
- `Slug history` and `Slug lock` lines in `php artisan about`.

### Removed

- The private `HasSluggableTranslations` trait and the custom `Post::resolveRouteBinding()`.
