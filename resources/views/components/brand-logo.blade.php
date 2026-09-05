@props([
    'size' => 'nav',
])

@php
    $logo = \App\Support\Media\BrandLogoSources::fromManifest()->forPreset($size);
@endphp

<img
    src="{{ $logo['src'] }}"
    srcset="{{ $logo['srcset'] }}"
    alt="{{ config('app.name') }}"
    width="{{ $logo['width'] }}"
    height="{{ $logo['height'] }}"
    decoding="async"
    {{ $attributes }}
/>
