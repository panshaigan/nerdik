@props(['sources', 'class' => null, 'loading' => 'lazy'])

@php
    /** @var \App\Support\Media\MediaPictureSources $sources */
@endphp

<picture @class(['block', $class, 'overflow-hidden'])>
    @if ($sources->avifSrcset() !== '')
        <source type="image/avif" srcset="{{ $sources->avifSrcset() }}" sizes="{{ $sources->sizes() }}">
    @endif
    @if ($sources->webpSrcset() !== '')
        <source type="image/webp" srcset="{{ $sources->webpSrcset() }}" sizes="{{ $sources->sizes() }}">
    @endif
    <img
        src="{{ $sources->jpegSrc() }}"
        @class([$class])
        @if ($sources->jpegSrcset() !== '')
            srcset="{{ $sources->jpegSrcset() }}"
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
        decoding="async"
    >
</picture>
