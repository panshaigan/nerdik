@props([
    'picture',
])

@teleport('body')
    <div
        {{ $attributes->class('pointer-events-none fixed inset-0 z-0 overflow-hidden') }}
        aria-hidden="true"
        wire:ignore
    >
        <div class="absolute inset-0 scale-105 blur-md">
            <x-listing-card-picture
                :picture="$picture"
                class="h-full w-full object-cover"
                loading="eager"
            />
        </div>
        <div class="absolute inset-0 bg-base-100/35"></div>
    </div>
@endteleport
