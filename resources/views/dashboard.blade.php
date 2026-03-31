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

            <section class="space-y-4">
                <div class="rounded-2xl border border-base-300 bg-base-100/90 p-6 shadow-xl">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-3">
                            <span class="inline-flex rounded-full bg-success/20 px-3 py-1 text-xs font-semibold text-success-content">
                                Completed
                            </span>
                            <h3 class="text-3xl font-bold tracking-tight">
                                Sesje RPG w Mistrzu i Malgorzacie - luty 2026
                            </h3>
                            <p class="text-sm opacity-70">Sunday, February 8 · 13:30-17:30</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
                            <div class="rounded-xl bg-emerald-900/40 p-4 text-center">
                                <p class="text-3xl font-bold text-emerald-300">{{ $myActivities->count() }}</p>
                                <p class="text-xs uppercase tracking-wide text-emerald-200">Sessions</p>
                            </div>
                            <div class="rounded-xl bg-rose-900/40 p-4 text-center">
                                <p class="text-3xl font-bold text-rose-300">{{ $participations->count() }}</p>
                                <p class="text-xs uppercase tracking-wide text-rose-200">Players</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="flex-1">
                        <label class="input input-bordered flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35m1.85-4.65a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                            </svg>
                            <input type="text" class="grow" placeholder="Filter by title or host..." />
                        </label>
                    </div>
                    <button class="btn btn-outline">Filters</button>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($myActivities->take(3) as $activity)
                        <article class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-lg">
                            <h4 class="text-2xl font-semibold">{{ $activity->name }}</h4>
                            <p class="mt-2 text-sm opacity-70">
                                {{ $activity->desc ?? 'Activity description preview. This card style is the design baseline for polish stage.' }}
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="badge badge-neutral">{{ ucfirst($activity->type) }}</span>
                                @if ($activity->age_limit)
                                    <span class="badge badge-outline">Age {{ $activity->age_limit }}+</span>
                                @endif
                            </div>
                            <div class="mt-5 flex items-center justify-between text-xs opacity-70">
                                <span>Table preview</span>
                                <a href="{{ route('activities.show', $activity) }}" class="link link-hover">Open</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

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
