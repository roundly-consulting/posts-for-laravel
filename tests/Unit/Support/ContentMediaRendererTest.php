<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Posts\Support\ContentMediaRenderer;

beforeEach(function (): void {
    $this->renderer = new ContentMediaRenderer;
});

it('extracts unique uuids in order of first appearance', function (): void {
    $a = '11111111-1111-1111-1111-111111111111';
    $b = '22222222-2222-2222-2222-222222222222';

    $content = "intro [media:{$a}] middle [media:{$b}|thumb] again [media:{$a}]";

    expect($this->renderer->extractUuids($content))->toBe([$a, $b]);
});

it('ignores malformed tokens when extracting uuids', function (): void {
    expect($this->renderer->extractUuids('text [media:not-a-uuid] more [media:123]'))->toBe([]);
});

it('returns content unchanged when there are no tokens', function (): void {
    $content = 'just some plain content with no media';

    expect($this->renderer->render($content, new Collection))->toBe($content);
});

it('strips tokens for missing media by default', function (): void {
    $uuid = '11111111-1111-1111-1111-111111111111';

    expect($this->renderer->render("a [media:{$uuid}] b", new Collection))->toBe('a  b');
});

it('keeps tokens for missing media when on_missing is keep', function (): void {
    $uuid = '11111111-1111-1111-1111-111111111111';
    $content = "a [media:{$uuid}] b";

    expect($this->renderer->render($content, new Collection, '', 'keep'))->toBe($content);
});

it('leaves malformed tokens untouched', function (): void {
    $content = 'keep [media:not-a-uuid] this';

    expect($this->renderer->render($content, new Collection))->toBe($content);
});
