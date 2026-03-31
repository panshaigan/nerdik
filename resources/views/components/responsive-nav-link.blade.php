@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full border-l-4 border-primary bg-primary/10 py-2 ps-3 pe-4 text-start text-base font-medium text-base-content focus:border-primary focus:bg-primary/20 focus:text-base-content focus:outline-none transition duration-150 ease-in-out'
            : 'block w-full border-l-4 border-transparent py-2 ps-3 pe-4 text-start text-base font-medium text-base-content/80 hover:border-base-300 hover:bg-base-200 hover:text-base-content focus:border-base-300 focus:bg-base-200 focus:text-base-content focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
