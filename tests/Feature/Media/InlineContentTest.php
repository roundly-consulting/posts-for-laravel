<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use RoundlyConsulting\MediaLibrary\Facades\Media;
use RoundlyConsulting\Posts\Models\Post;

beforeEach(function (): void {
    Storage::fake('public');
});

function postWithContentImage(string $file = 'inline.jpg'): array
{
    $post = Post::factory()->create();

    $media = $post->addMedia(UploadedFile::fake()->image($file, 800, 600))
        ->toMediaBucket($post->contentBucket());

    return [$post, $media];
}

it('renders an image token as a responsive img tag', function (): void {
    [$post, $media] = postWithContentImage();

    $post->setTranslation('content', 'en', "before [media:{$media->uuid}] after")->save();

    $html = (string) $post->renderContent();

    expect($html)->toContain('<img');
    expect($html)->toContain('srcset=');
});

it('honors an explicit variant in the rendered url', function (): void {
    [$post, $media] = postWithContentImage();

    $post->setTranslation('content', 'en', "x [media:{$media->uuid}|responsive-320] y")->save();

    $html = (string) $post->renderContent();

    expect($html)->toContain('<img');
    expect($html)->toContain('responsive-320');
});

it('renders a non-image token as a link', function (): void {
    $post = Post::factory()->create();

    $media = $post->addMedia(UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'))
        ->toMediaBucket($post->contentBucket());

    $post->setTranslation('content', 'en', "see [media:{$media->uuid}] now")->save();

    $html = (string) $post->renderContent();

    expect($html)->toContain('<a href=');
    expect($html)->toContain($media->name);
});

it('resolves multiple tokens in a single query', function (): void {
    $post = Post::factory()->create();

    $one = $post->addMedia(UploadedFile::fake()->image('1.jpg', 400, 300))->toMediaBucket($post->contentBucket());
    $two = $post->addMedia(UploadedFile::fake()->image('2.jpg', 400, 300))->toMediaBucket($post->contentBucket());

    $post->setTranslation('content', 'en', "[media:{$one->uuid}] and [media:{$two->uuid}]")->save();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $post->renderContent();

    expect(DB::getQueryLog())->toHaveCount(1);

    DB::disableQueryLog();
});

it('strips a token whose uuid is not owned by the post', function (): void {
    $post = Post::factory()->create();

    // Global media (not in the post's content bucket) must never resolve inline.
    $global = Media::add(UploadedFile::fake()->image('global.jpg', 400, 300))->toBucket('content');

    $post->setTranslation('content', 'en', "a [media:{$global->uuid}] b")->save();

    expect((string) $post->renderContent())->toBe('a  b');
});

it('keeps a missing token when on_missing is keep', function (): void {
    config()->set('posts.media.inline.on_missing', 'keep');

    $post = Post::factory()->create();
    $uuid = '11111111-1111-1111-1111-111111111111';

    $post->setTranslation('content', 'en', "a [media:{$uuid}] b")->save();

    expect((string) $post->renderContent())->toBe("a [media:{$uuid}] b");
});

it('returns content unchanged when inline rendering is disabled', function (): void {
    config()->set('posts.media.inline.enabled', false);

    [$post, $media] = postWithContentImage();
    $body = "raw [media:{$media->uuid}] body";

    $post->setTranslation('content', 'en', $body)->save();

    expect((string) $post->renderContent())->toBe($body);
});

it('renders the requested locale translation', function (): void {
    [$post, $media] = postWithContentImage();

    $post->setTranslation('content', 'en', 'english body')
        ->setTranslation('content', 'sk', "slovak [media:{$media->uuid}]")
        ->save();

    $html = (string) $post->renderContent('sk');

    expect($html)->toContain('slovak');
    expect($html)->toContain('<img');
});

it('leaves malformed tokens untouched', function (): void {
    $post = Post::factory()->create();

    $post->setTranslation('content', 'en', 'keep [media:not-a-uuid] this')->save();

    expect((string) $post->renderContent())->toBe('keep [media:not-a-uuid] this');
});

it('returns the stored body as raw HTML, verbatim — it never sanitizes', function (bool $inline): void {
    // Documented contract: renderContent() is raw authored HTML. Hosts sanitize untrusted rich
    // text at write time; the package neither strips nor escapes markup on render.
    config()->set('posts.media.inline.enabled', $inline);

    $body = '<p onclick="x()">Hi &amp; <script>alert(1)</script><a href="javascript:y()">z</a></p>';
    $post = Post::factory()->create();
    $post->setTranslation('content', 'en', $body)->save();

    $html = $post->fresh()?->renderContent();

    expect($html)->toBeInstanceOf(HtmlString::class)
        ->and((string) $html)->toBe($body);
})->with(['inline media on' => true, 'inline media off' => false]);
