<div class="py-12 max-w-lg mx-auto">
    @if ($notifications->total() > 0)
        <div class="mb-4">
            <x-button type="button" class="btn-ghost btn-sm" wire:click="markAllRead" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="markAllRead">{{ __('Mark all as read') }}</span>
                <span wire:loading wire:target="markAllRead">{{ __('Updating…') }}</span>
            </x-button>
        </div>
    @endif

        @forelse ($notifications as $notification)
            @php
                $display = $displays[$notification->id];
            @endphp
            <div @class(['border-b border-base-300 last:border-b-0', 'bg-primary/5' => $display->isUnread])>
                <x-button
                    type="button"
                    class="btn-ghost h-auto min-h-0 w-full justify-start rounded-none border-0 px-4 py-0 font-normal normal-case text-start shadow-none hover:bg-base-200/50"
                    wire:click="markReadAndGo('{{ $notification->id }}')"
                    wire:loading.attr="disabled"
                    wire:target="markReadAndGo"
                >
                    <x-timeline-item
                        :id="$notification->id"
                        :title="$display->title"
                        :subtitle="$display->timeAgo"
                        :description="$display->subtitle"
                        :icon="$display->icon"
                        :pending="$display->isUnread"
                        :first="$loop->first"
                        :last="$loop->last"
                    />
                </x-button>
            </div>
        @empty
            <p class="p-6 text-center opacity-70">{{ __('No notifications yet.') }}</p>
        @endforelse

        @if ($notifications->hasPages())
            <div class="border-t border-base-300 p-4">{{ $notifications->links() }}</div>
        @endif
</div>
