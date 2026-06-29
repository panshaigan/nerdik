@props(['sources', 'class' => null])

@php
    /** @var \App\Support\Media\StaticPictureSources $sources */
@endphp

<picture @class(['block h-full w-full overflow-hidden', $class])>
    @if ($sources->webpMobileSrcset() !== '')
        <source
            type="image/webp"
            media="{{ $sources->mobileMediaQuery() }}"
            srcset="{{ $sources->webpMobileSrcset() }}"
            sizes="{{ $sources->sizes() }}"
        >
    @endif
    @if ($sources->webpDesktopSrcset() !== '')
        <source
            type="image/webp"
            media="{{ $sources->desktopMediaQuery() }}"
            srcset="{{ $sources->webpDesktopSrcset() }}"
            sizes="{{ $sources->sizes() }}"
        >
    @endif
    <img
        src="{{ $sources->displaySrc() }}"
        @class(['h-full w-full object-cover', $class])
        sizes="{{ $sources->sizes() }}"
        alt="{{ $sources->alt() }}"
        @if ($sources->width())
            width="{{ $sources->width() }}"
        @endif
        @if ($sources->height())
            height="{{ $sources->height() }}"
        @endif
        loading="eager"
        fetchpriority="high"
        decoding="async"
    >
</picture>
