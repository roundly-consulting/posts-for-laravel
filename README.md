<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/posts-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=posts-for-laravel">
    <img src="art/hero.png" alt="Posts for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/posts-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/posts-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/posts-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/posts-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/posts-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/posts-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=posts-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Posts for Laravel

A multilingual, SEO-ready blog engine for Laravel: translatable posts with a real publishing
lifecycle (draft, scheduled, published, archived), nested categories and tags, per-locale slugs,
SEO meta and schema.org structured data, and a polymorphic author of any key type. Featured
images, likes and reports come built in through our own media, likes and reports packages.

## Installation

Requires PHP 8.4 and Laravel 12 or 13.

```bash
composer require roundly-consulting/posts-for-laravel
php artisan vendor:publish --tag="posts-migrations"
php artisan vendor:publish --tag="media-migrations"
php artisan vendor:publish --tag="likes-migrations"
php artisan vendor:publish --tag="reports-migrations"
php artisan vendor:publish --tag="approvals-migrations"
php artisan migrate
```

If your author models are UUID/ULID-keyed, set `POSTS_KEY_TYPE` (and `POSTS_PRIMARY_KEY_TYPE` for
the posts' own ids) **before** migrating. Scheduled posts go live through
`Schedule::command('posts:publish-scheduled')->everyMinute();`.

## Usage

Write a post in two languages and publish it (or `->save()` a draft, `->schedule($at)` it):

```php
use RoundlyConsulting\Posts\DataTransferObjects\SeoData;
use RoundlyConsulting\Posts\Facades\Posts;

$post = Posts::draft()
    ->title('en', 'Hello world')->title('sk', 'Ahoj svet')
    ->perex('en', 'A short intro')
    ->content('en', '<p>…</p>')
    ->by($user)                                   // any author model, bigint/uuid/ulid keyed
    ->tags(['laravel', 'php'])                    // found or created by name
    ->seo(new SeoData(canonical: 'https://example.test/hello-world'))
    ->publish();

$post->getTranslation('title', 'sk');             // 'Ahoj svet'
```

Read it back — slugs are generated per locale, and `{post}` route parameters bind by them:

```php
use RoundlyConsulting\Posts\Models\Post;

Posts::findBySlug('hello-world');                 // current locale → fallback → any locale
Posts::published()->latest('published_at')->paginate();
Posts::archive($post);                            // or unpublish() back to draft

Route::get('/posts/{post}', fn (Post $post) => view('posts.show', compact('post')))->name('posts.show');
```

And render it with its SEO tags and JSON-LD:

```blade
{!! $post->renderMetaTags() !!}   {{-- <title>, description, canonical, og:*, twitter:*, robots --}}
{!! $post->renderJsonLd() !!}     {{-- schema.org BlogPosting --}}

<h1>{{ $post->title }}</h1>        {{-- current locale, with fallback --}}
{!! $post->renderContent() !!}    {{-- raw HTML: trusted or sanitized content only --}}
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/posts-for-laravel](https://roundly-consulting.com/open-source/docs/posts-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=posts-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=posts-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=posts-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
