@props([
    'size' => 'nav',
    'href' => null,
])

@php
    $logo = \App\Support\Media\BrandLogoSources::fromManifest()->forPreset($size);
    $name = (string) config('app.name');
    $isLink = is_string($href) && $href !== '';
@endphp

<{{ $isLink ? 'a' : 'span' }}
    @if ($isLink)
        href="{{ $href }}"
    @endif
    {{ $attributes->class('ui-brand inline-flex items-center gap-[0.2em] text-inherit no-underline') }}
    style="font-size: {{ $logo['wordmark_font_size'] }}px"
>
    <x-brand-logo :size="$size" :alt="''" class="block h-auto w-auto shrink-0" />
    <span class="ui-brand-name font-display font-semibold leading-none">{{ $name }}</span>
</{{ $isLink ? 'a' : 'span' }}>
