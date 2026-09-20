@php
    $triggerClass = $variant === 'mobile'
        ? 'relative btn btn-ghost btn-square rounded-md opacity-70 transition duration-150 ease-in-out hover:bg-base-200 hover:opacity-100 focus:outline-none'
        : 'relative btn btn-circle btn-ghost';
    $iconClass = $variant === 'mobile' ? 'h-6 w-6' : 'h-5 w-5';
@endphp

<div>
    @if ($hasAnyRequests)
        <div class="dropdown dropdown-end relative z-50" data-ui="nav-requests">
            <div
                tabindex="0"
                role="button"
                class="{{ $triggerClass }}"
                aria-label="{{ __('ui.nav.requests') }}"
                aria-haspopup="true"
                data-ui="nav-requests-trigger"
            >
                <x-mary-icon name="o-inbox-arrow-down" class="{{ $iconClass }}" />
                @if ($pendingBadge !== null)
                    <span
                        class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-secondary text-[10px] font-medium text-secondary-content"
                        data-ui="nav-requests-badge"
                    >
                        {{ $pendingBadge }}
                    </span>
                @endif
            </div>

            <div
                tabindex="0"
                class="dropdown-content z-[100] mt-3 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-lg light:border-neutral max-sm:fixed max-sm:!start-3 max-sm:!end-3 max-sm:!w-auto max-sm:translate-x-0"
                data-ui="nav-requests-panel"
            >
                <div class="border-b border-base-300 px-4 py-3 light:border-neutral">
                    <p class="text-sm font-semibold">{{ __('ui.requests.dropdown_heading') }}</p>
                </div>

                <div class="max-h-80 overflow-y-auto">
                    @forelse ($requests as $request)
                        @php
                            $display = $displays[$request->id];
                        @endphp
                        <button
                            type="button"
                            wire:key="nav-request-{{ $request->id }}"
                            wire:click="openRequest({{ $request->id }})"
                            wire:loading.attr="disabled"
                            wire:target="openRequest"
                            class="flex w-full gap-3 border-b border-base-300 px-4 py-3 text-start last:border-b-0 hover:bg-base-200/50 light:border-neutral {{ $display['needsResponse'] ? 'bg-secondary/10' : '' }}"
                            data-ui="nav-request-item"
                        >
                            <x-mary-icon name="o-inbox-arrow-down" class="mt-0.5 h-5 w-5 shrink-0 opacity-70" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $display['title'] }}</span>
                                @if ($display['subtitle'])
                                    <span class="mt-0.5 block truncate text-xs text-base-content/70">{{ $display['subtitle'] }}</span>
                                @endif
                                <span class="mt-0.5 block text-xs text-base-content/50">{{ $display['timeAgo'] }}</span>
                            </span>
                        </button>
                    @empty
                        <p class="px-4 py-6 text-center text-sm opacity-70">{{ __('ui.user_requests.incoming_empty') }}</p>
                    @endforelse
                </div>

                <a
                    href="{{ route('requests.index') }}"
                    wire:navigate
                    class="block border-t border-base-300 px-4 py-3 text-center text-sm font-medium text-primary hover:bg-base-200/50 light:border-neutral"
                    data-ui="nav-requests-view-all"
                >
                    {{ __('ui.requests.view_all') }}
                </a>
            </div>
        </div>
    @endif
</div>
