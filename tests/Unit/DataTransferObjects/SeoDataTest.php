<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\DataTransferObjects\SeoData;

it('maps the bag back and forth', function (): void {
    $data = new SeoData(
        canonical: 'https://example.test',
        ogTitle: 'OG Title',
        ogImage: 'https://img.test/a.png',
        twitterCard: 'summary',
        robots: 'index,follow',
    );

    $bag = $data->toBag();

    expect($bag['canonical'])->toBe('https://example.test')
        ->and($bag['og_title'])->toBe('OG Title')
        ->and($bag['twitter_card'])->toBe('summary');

    $rebuilt = SeoData::fromBag($bag, 'Meta', 'Desc');

    expect($rebuilt->metaTitle)->toBe('Meta')
        ->and($rebuilt->metaDescription)->toBe('Desc')
        ->and($rebuilt->canonical)->toBe('https://example.test')
        ->and($rebuilt->ogImage)->toBe('https://img.test/a.png');
});

it('treats empty strings in the bag as null', function (): void {
    $data = SeoData::fromBag(['canonical' => '', 'og_title' => 'Set']);

    expect($data->canonical)->toBeNull()
        ->and($data->ogTitle)->toBe('Set');
});
