<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Notifications') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div role="alert" class="alert alert-success mb-4 text-sm">{{ session('status') }}</div>
            @endif

            @if ($notifications->total() > 0)
                <form action="{{ route('notifications.mark-all-read') }}" method="POST" class="mb-4">
                    @csrf
                    <x-button type="submit" class="btn-ghost btn-sm">{{ __('Mark all as read') }}</x-button>
                </form>
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
                            <form id="notif-{{ $notification->id }}" action="{{ route('notifications.mark-read', $notification->id) }}" method="POST" class="block">
                                @csrf
                                <button type="submit" class="w-full text-left">
                                @if ($type === 'proposal_accepted')
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
                                </button>
                            </form>
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
</x-app-layout>
