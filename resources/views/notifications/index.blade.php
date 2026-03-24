<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Notifications') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="mb-4 text-sm text-green-600">{{ session('status') }}</p>
            @endif

            @if ($notifications->total() > 0)
                <form action="{{ route('notifications.mark-all-read') }}" method="POST" class="mb-4">
                    @csrf
                    <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">
                        {{ __('Mark all as read') }}
                    </button>
                </form>
            @endif

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <ul class="divide-y divide-gray-200">
                    @forelse ($notifications as $notification)
                        @php
                            $d = $notification->data;
                            $type = $d['type'] ?? 'unknown';
                            $url = $d['url'] ?? route('dashboard');
                            $isUnread = $notification->read_at === null;
                        @endphp
                        <li class="p-4 {{ $isUnread ? 'bg-indigo-50/50' : '' }}">
                            <form id="notif-{{ $notification->id }}" action="{{ route('notifications.mark-read', $notification->id) }}" method="POST" class="block">
                                @csrf
                                <button type="submit" class="w-full text-left">
                                @if ($type === 'proposal_accepted')
                                    <span class="font-medium text-gray-900">{{ __('Proposal accepted') }}</span>
                                    <span class="text-gray-600"> – {{ $d['activity_name'] ?? '' }}</span>
                                    @if (!empty($d['event_name']))
                                        <span class="text-gray-500 text-sm"> · {{ $d['event_name'] }}</span>
                                    @endif
                                @elseif ($type === 'proposal_rejected')
                                    <span class="font-medium text-gray-900">{{ __('Proposal rejected') }}</span>
                                    <span class="text-gray-600"> – {{ $d['activity_name'] ?? '' }}</span>
                                    @if (!empty($d['event_name']))
                                        <span class="text-gray-500 text-sm"> · {{ $d['event_name'] }}</span>
                                    @endif
                                @elseif ($type === 'waitlist_promoted')
                                    <span class="font-medium text-gray-900">{{ __('You got a place!') }}</span>
                                    <span class="text-gray-600"> – {{ $d['activity_name'] ?? '' }}</span>
                                @else
                                    <span class="text-gray-700">{{ json_encode($d) }}</span>
                                @endif
                                    <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                                </button>
                            </form>
                        </li>
                    @empty
                        <li class="p-6 text-center text-gray-500">{{ __('No notifications yet.') }}</li>
                    @endforelse
                </ul>
                @if ($notifications->hasPages())
                    <div class="p-4 border-t">{{ $notifications->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
