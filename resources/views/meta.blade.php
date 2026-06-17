@php
    /** @var \RoundlyConsulting\Posts\DataTransferObjects\SeoData $seo */
@endphp
@if($seo->metaTitle)
<title>{{ $seo->metaTitle }}</title>
@endif
@if($seo->metaDescription)
<meta name="description" content="{{ $seo->metaDescription }}">
@endif
@if($seo->robots)
<meta name="robots" content="{{ $seo->robots }}">
@endif
@if($seo->canonical)
<link rel="canonical" href="{{ $seo->canonical }}">
@endif
@if($seo->ogTitle)
<meta property="og:title" content="{{ $seo->ogTitle }}">
@endif
@if($seo->ogDescription)
<meta property="og:description" content="{{ $seo->ogDescription }}">
@endif
@if($seo->ogType)
<meta property="og:type" content="{{ $seo->ogType }}">
@endif
@if($seo->ogImage)
<meta property="og:image" content="{{ $seo->ogImage }}">
@endif
@if($seo->twitterCard)
<meta name="twitter:card" content="{{ $seo->twitterCard }}">
@endif
@if($seo->twitterSite)
<meta name="twitter:site" content="{{ $seo->twitterSite }}">
@endif
@if($seo->twitterCreator)
<meta name="twitter:creator" content="{{ $seo->twitterCreator }}">
@endif
