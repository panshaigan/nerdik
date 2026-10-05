@props([
    'picture',
    'loading' => 'lazy',
])

<div
    {{ $attributes->class([
        'ui-cover-backdrop pointer-events-none absolute inset-0 z-0',
    ]) }}
    aria-hidden="true"
>
    <x-listing-card-picture
        :picture="$picture"
        class="ui-cover-backdrop__image"
        :loading="$loading"
    />
</div>
