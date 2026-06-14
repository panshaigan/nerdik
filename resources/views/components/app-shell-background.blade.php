@props(['enabled' => true])

@if ($enabled)
    <div
        class="pointer-events-none fixed inset-0 -z-10 overflow-hidden bg-[#00050a]"
        data-ui="app-shell-background"
        aria-hidden="true"
    >
        <img
            src="{{ asset('images/app/background.webp') }}"
            alt=""
            class="h-full w-full object-cover"
            loading="eager"
            fetchpriority="high"
        />
    </div>
@endif
