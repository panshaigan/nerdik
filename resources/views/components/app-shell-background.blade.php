@props(['enabled' => true])

@if ($enabled)
    <div
        class="pointer-events-none fixed inset-0 z-0 overflow-hidden bg-[#00050a] light:bg-[#f5f0e8]"
        data-ui="app-shell-background"
        aria-hidden="true"
    >
        <img
            src="{{ asset('images/app/background.webp') }}"
            alt=""
            class="h-full w-full object-cover light:hidden"
            loading="eager"
            fetchpriority="high"
        />
        <img
            src="{{ asset('images/app/background-light.webp') }}"
            alt=""
            class="hidden h-full w-full object-cover light:block"
            loading="eager"
            fetchpriority="high"
        />
    </div>
@endif
