@props([
    'size' => 'nav',
    'href' => null,
    'wordmarkRatio' => null,
])

@php
    $resolvedRatio = $wordmarkRatio !== null ? (float) $wordmarkRatio : null;
    $logo = \App\Support\Media\BrandLogoSources::fromManifest()->forPreset($size, $resolvedRatio);
    $name = (string) config('app.name');
    $isLink = is_string($href) && $href !== '';
@endphp

<{{ $isLink ? 'a' : 'span' }}
    @if ($isLink)
        href="{{ $href }}"
    @endif
    {{ $attributes->class('ui-brand inline-flex items-center gap-[0.2em] text-inherit no-underline') }}
    style="--brand-logo-height: {{ $logo['height'] }}px; --brand-wordmark-ratio: {{ $logo['wordmark_ratio'] }}"
>
    <x-brand-logo
        :size="$size"
        :alt="''"
        class="ui-brand-mark block w-auto shrink-0"
        style="height: var(--brand-logo-height); width: auto"
    />
    <span
        class="ui-brand-name font-display font-semibold leading-none"
        style="font-size: calc(var(--brand-logo-height) * var(--brand-wordmark-ratio))"
    >{{ $name }}</span>
</{{ $isLink ? 'a' : 'span' }}>
