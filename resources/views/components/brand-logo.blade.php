@props([
    'size' => 'nav',
    'alt' => null,
])

@php
    $logo = \App\Support\Media\BrandLogoSources::fromManifest()->forPreset($size);
    $resolvedAlt = $alt ?? (string) config('app.name');
@endphp

<img
    src="{{ $logo['src'] }}"
    srcset="{{ $logo['srcset'] }}"
    alt="{{ $resolvedAlt }}"
    width="{{ $logo['width'] }}"
    height="{{ $logo['height'] }}"
    decoding="async"
    {{ $attributes }}
/>
