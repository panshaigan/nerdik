@props(['sources', 'class' => null, 'loading' => 'lazy', 'fetchpriority' => null])

@php
    /** @var \App\Support\Media\MediaPictureSources|\App\Support\Media\StaticPictureSources $sources */
@endphp

<picture @class(['block', $class, 'overflow-hidden'])>
    @if ($sources->avifSrcset() !== '')
        <source type="image/avif" srcset="{{ $sources->avifSrcset() }}" sizes="{{ $sources->sizes() }}">
    @endif
    @if ($sources->webpSrcset() !== '')
        <source type="image/webp" srcset="{{ $sources->webpSrcset() }}" sizes="{{ $sources->sizes() }}">
    @endif
    <img
        src="{{ $sources->displaySrc() }}"
        @class([$class])
        @if ($sources->webpSrcset() !== '')
            srcset="{{ $sources->webpSrcset() }}"
        @endif
        sizes="{{ $sources->sizes() }}"
        alt="{{ $sources->alt() }}"
        @if ($sources->width())
            width="{{ $sources->width() }}"
        @endif
        @if ($sources->height())
            height="{{ $sources->height() }}"
        @endif
        loading="{{ $loading }}"
        @if ($fetchpriority)
            fetchpriority="{{ $fetchpriority }}"
        @endif
        decoding="async"
    >
</picture>
