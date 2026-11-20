<div class="dropdown dropdown-end relative z-50" data-ui="nav-notifications">
    <div
        tabindex="0"
        role="button"
        class="relative btn btn-ghost btn-square sm:btn-circle rounded-md sm:rounded-full opacity-70 sm:opacity-100 transition duration-150 ease-in-out hover:bg-base-200 hover:opacity-100 focus:outline-none"
        aria-label="{{ __('ui.nav.notifications') }}"
        aria-haspopup="true"
        data-ui="nav-notifications-trigger"
    >
        <x-mary-icon name="o-bell" class="h-6 w-6 sm:h-5 sm:w-5" />
        @if ($unreadCount > 0)
            <span
                class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[10px] font-medium text-primary-content"
                data-ui="nav-notifications-badge"
            >
                {{ $unreadBadge }}
            </span>
        @endif
    </div>

    <div
        tabindex="0"
        class="dropdown-content z-[100] mt-3 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-lg light:border-neutral max-sm:fixed max-sm:!start-3 max-sm:!end-3 max-sm:!w-auto max-sm:translate-x-0"
        data-ui="nav-notifications-panel"
    >
        <div class="border-b border-base-300 px-4 py-3 light:border-neutral">
            <p class="text-sm font-semibold">{{ __('ui.notifications.dropdown_heading') }}</p>
        </div>

        <div class="max-h-80 overflow-y-auto">
            @forelse ($notifications as $notification)
                @php
                    $display = $displays[$notification->id];
                @endphp
                <button
                    type="button"
                    wire:key="nav-notification-{{ $notification->id }}"
                    wire:click="handleNotificationClick('{{ $notification->id }}')"
                    wire:loading.attr="disabled"
                    wire:target="handleNotificationClick"
                    @class([
                        'flex w-full gap-3 border-b border-base-300 px-4 py-3 text-start last:border-b-0 hover:bg-base-200/50 light:border-neutral',
                        'border-s-2 border-s-primary bg-primary/5' => $display->isUnread,
                    ])
                    data-ui="nav-notification-item"
                    @if ($display->isUnread) data-notification-unread="true" @endif
                >
                    @if ($display->icon)
                        <x-mary-icon name="{{ $display->icon }}" class="mt-0.5 h-5 w-5 shrink-0 opacity-70" />
                    @endif
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium">{{ $display->title }}</span>
                        @if ($display->subtitle)
                            <span class="mt-0.5 block truncate text-xs text-base-content/70">{{ $display->subtitle }}</span>
                        @endif
                        <span class="mt-0.5 block text-xs text-base-content/50">{{ $display->timeAgo }}</span>
                    </span>
                </button>
            @empty
                <p class="px-4 py-6 text-center text-sm opacity-70">{{ __('ui.notifications.empty') }}</p>
            @endforelse
        </div>

        <div
            class="grid grid-cols-2 divide-x divide-base-300 border-t border-base-300 light:divide-neutral light:border-neutral"
            data-ui="nav-notifications-footer"
        >
            <button
                type="button"
                wire:click="markAllRead"
                wire:loading.attr="disabled"
                wire:target="markAllRead"
                @class([
                    'px-2 py-3 text-center text-xs font-medium hover:bg-base-200/50 sm:px-3 sm:text-sm',
                    'font-medium text-base-content/80' => $unreadCount > 0,
                    'text-base-content/60' => $unreadCount === 0,
                ])
                data-ui="nav-notifications-clear-all"
            >
                <span wire:loading.remove wire:target="markAllRead">{{ __('ui.notifications.clear_all') }}</span>
                <span wire:loading wire:target="markAllRead">{{ __('ui.notifications.updating') }}</span>
            </button>
            <a
                href="{{ route('notifications.index') }}"
                wire:navigate
                class="px-2 py-3 text-center text-xs font-medium text-primary hover:bg-base-200/50 sm:px-3 sm:text-sm"
                data-ui="nav-notifications-view-all"
            >
                {{ __('ui.notifications.view_all_short') }}
            </a>
        </div>
    </div>
</div>
