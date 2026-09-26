@php
    /** @var array<string, mixed> $data */
    // Inside <script> the HTML parser ends the element at the first `</script` and honours
    // `<!--`, so every `<`, `>`, `&`, `'` and `"` inside a string is hex-escaped (< …):
    // stored titles, names and tags can never break out, and the JSON-LD stays valid.
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
@endphp
<script type="application/ld+json">{!! json_encode($data, $flags) !!}</script>
