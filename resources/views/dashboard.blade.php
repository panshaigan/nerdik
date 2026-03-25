<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))
                <p class="text-sm text-green-600">{{ session('status') }}</p>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">{{ __('My events') }}</h3>
                    @if ($myEvents->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No events yet.') }}</p>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($myEvents as $event)
                                <li class="py-2">
                                    <a href="{{ route('events.edit', $event) }}" class="text-indigo-600 hover:text-indigo-900">
                                        {{ $event->name }}
                                    </a>
                                    @if ($event->organization)
                                        <span class="text-gray-500 text-sm"> · {{ $event->organization->name }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('events.index') }}" class="mt-2 inline-block text-sm text-gray-600 hover:text-gray-900">
                            {{ __('All events') }} →
                        </a>
                    @endif
                </div>

                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">{{ __('My activities (as host)') }}</h3>
                    @if ($myActivities->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No activities yet.') }}</p>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($myActivities as $activity)
                                <li class="py-2">
                                    <a href="{{ route('activities.show', $activity) }}" class="text-indigo-600 hover:text-indigo-900">
                                        {{ $activity->name }}
                                    </a>
                                    <span class="text-gray-500 text-sm"> · {{ ucfirst($activity->type) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('activities.index') }}" class="mt-2 inline-block text-sm text-gray-600 hover:text-gray-900">
                            {{ __('All activities') }} →
                        </a>
                    @endif
                </div>

                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">{{ __('Participating') }}</h3>
                    @if ($participations->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('You are not participating in any activity yet.') }}</p>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($participations as $p)
                                <li class="py-2">
                                    <a href="{{ route('activities.show', $p->activity) }}" class="text-indigo-600 hover:text-indigo-900">
                                        {{ $p->activity->name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('activities.index') }}" class="mt-2 inline-block text-sm text-gray-600 hover:text-gray-900">
                            {{ __('Browse activities') }} →
                        </a>
                    @endif
                </div>

                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">{{ __('My proposals') }}</h3>
                    @if ($myProposals->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No proposals yet.') }}</p>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($myProposals as $proposal)
                                <li class="py-2">
                                    <span class="font-medium">{{ $proposal->activity->name }}</span>
                                    <span class="text-gray-500 text-sm">
                                        → {{ $proposal->event->name }}
                                        ({{ ucfirst($proposal->status) }})
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('activity-proposals.index') }}" class="mt-2 inline-block text-sm text-gray-600 hover:text-gray-900">
                            {{ __('All proposals') }} →
                        </a>
                    @endif
                </div>

                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">{{ __('Wishlist – events') }}</h3>
                    @if ($wishlistEvents->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No events in wishlist.') }}</p>
                        <a href="{{ route('browse.events') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:text-indigo-900">{{ __('Browse events') }} →</a>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($wishlistEvents as $event)
                                <li class="py-2 flex items-center justify-between">
                                    <a href="{{ route('events.show', $event) }}" class="text-indigo-600 hover:text-indigo-900">{{ $event->name }}</a>
                                    <form action="{{ route('wishlist.events.remove', $event) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-gray-500 hover:text-red-600">{{ __('Remove') }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('browse.events') }}" class="mt-2 inline-block text-sm text-gray-600 hover:text-gray-900">{{ __('Browse events') }} →</a>
                    @endif
                </div>

                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">{{ __('Wishlist – activities') }}</h3>
                    @if ($wishlistActivities->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No activities in wishlist.') }}</p>
                        <a href="{{ route('browse.activities') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:text-indigo-900">{{ __('Browse activities') }} →</a>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($wishlistActivities as $activity)
                                <li class="py-2 flex items-center justify-between">
                                    <a href="{{ route('activities.show', $activity) }}" class="text-indigo-600 hover:text-indigo-900">{{ $activity->name }}</a>
                                    <form action="{{ route('wishlist.activities.remove', $activity) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-gray-500 hover:text-red-600">{{ __('Remove') }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('browse.activities') }}" class="mt-2 inline-block text-sm text-gray-600 hover:text-gray-900">{{ __('Browse activities') }} →</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
