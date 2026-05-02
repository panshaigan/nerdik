<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="alert" class="alert alert-success mb-4 text-sm">{{ session('status') }}</div>
        @endif

        @if ($notifications->total() > 0)
            <div class="mb-4">
                <x-button type="button" class="btn-ghost btn-sm" wire:click="markAllRead" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="markAllRead">{{ __('Mark all as read') }}</span>
                    <span wire:loading wire:target="markAllRead">{{ __('Updating…') }}</span>
                </x-button>
            </div>
        @endif

        <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
            <ul class="divide-y divide-base-300">
                @forelse ($notifications as $notification)
                    @php
                        $d = $notification->data;
                        $type = $d['type'] ?? 'unknown';
                        $isUnread = $notification->read_at === null;
                    @endphp
                    <li class="p-4 {{ $isUnread ? 'bg-primary/10' : '' }}">
                        <x-button
                            type="button"
                            class="btn-ghost h-auto min-h-0 w-full justify-start rounded-none border-0 font-normal normal-case text-start shadow-none hover:bg-base-200/50"
                            wire:click="markReadAndGo('{{ $notification->id }}')"
                            wire:loading.attr="disabled"
                            wire:target="markReadAndGo"
                        >
                            @if ($type === 'proposal_submitted')
                                <span class="font-medium text-base-content">{{ __('ui.notifications.proposal_submitted_list') }}</span>
                                <span class="opacity-80"> – {{ $d['activity_name'] ?? '' }}</span>
                                @if (!empty($d['event_name']))
                                    <span class="text-sm opacity-70"> · {{ $d['event_name'] }}</span>
                                @endif
                            @elseif ($type === 'proposal_accepted')
                                <span class="font-medium text-base-content">{{ __('Proposal accepted') }}</span>
                                <span class="opacity-80"> – {{ $d['activity_name'] ?? '' }}</span>
                                @if (!empty($d['event_name']))
                                    <span class="text-sm opacity-70"> · {{ $d['event_name'] }}</span>
                                @endif
                            @elseif ($type === 'proposal_rejected')
                                <span class="font-medium text-base-content">{{ __('Proposal rejected') }}</span>
                                <span class="opacity-80"> – {{ $d['activity_name'] ?? '' }}</span>
                                @if (!empty($d['event_name']))
                                    <span class="text-sm opacity-70"> · {{ $d['event_name'] }}</span>
                                @endif
                            @elseif ($type === 'waitlist_promoted')
                                <span class="font-medium text-base-content">{{ __('You got a place!') }}</span>
                                <span class="opacity-80"> – {{ $d['activity_name'] ?? '' }}</span>
                            @else
                                <span class="opacity-90">{{ json_encode($d) }}</span>
                            @endif
                            <p class="mt-1 text-xs opacity-60">{{ $notification->created_at->diffForHumans() }}</p>
                        </x-button>
                    </li>
                @empty
                    <li class="p-6 text-center opacity-70">{{ __('No notifications yet.') }}</li>
                @endforelse
            </ul>
            @if ($notifications->hasPages())
                <div class="border-t border-base-300 p-4">{{ $notifications->links() }}</div>
            @endif
        </div>
    </div>
</div>
