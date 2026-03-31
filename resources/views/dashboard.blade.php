<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
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
                    @forelse ($myActivities->take(3) as $activity)
                        <x-cards.activity-card :activity="$activity" />
                    @empty
                        <div class="col-span-full rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                            {{ __('No activities yet.') }}
                        </div>
                    @endforelse
                </div>
            </section>

            <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
                <div class="p-6 text-base-content">
                    {{ __("You're logged in!") }}
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                    <h3 class="mb-3 text-lg font-medium text-base-content">{{ __('My events') }}</h3>
                    @if ($myEvents->isEmpty())
                        <p class="text-sm opacity-70">{{ __('No events yet.') }}</p>
                    @else
                        <ul class="divide-y divide-base-300">
                            @foreach ($myEvents as $event)
                                <li class="py-2">
                                    <a href="{{ route('events.edit', $event) }}" class="link link-primary">
                                        {{ $event->name }}
                                    </a>
                                    @if ($event->organization)
                                        <span class="text-sm opacity-70"> · {{ $event->organization->name }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('events.index') }}" class="link link-hover mt-2 inline-block text-sm opacity-80">
                            {{ __('All events') }} →
                        </a>
                    @endif
                </div>

                <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                    <h3 class="mb-3 text-lg font-medium text-base-content">{{ __('My activities (as host)') }}</h3>
                    @if ($myActivities->isEmpty())
                        <p class="text-sm opacity-70">{{ __('No activities yet.') }}</p>
                    @else
                        <ul class="divide-y divide-base-300">
                            @foreach ($myActivities as $activity)
                                <li class="py-2">
                                    <a href="{{ route('activities.show', $activity) }}" class="link link-primary">
                                        {{ $activity->name }}
                                    </a>
                                    <span class="text-sm opacity-70"> · {{ ucfirst($activity->type) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('activities.index') }}" class="link link-hover mt-2 inline-block text-sm opacity-80">
                            {{ __('All activities') }} →
                        </a>
                    @endif
                </div>

                <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                    <h3 class="mb-3 text-lg font-medium text-base-content">{{ __('Participating') }}</h3>
                    @if ($participations->isEmpty())
                        <p class="text-sm opacity-70">{{ __('You are not participating in any activity yet.') }}</p>
                    @else
                        <ul class="divide-y divide-base-300">
                            @foreach ($participations as $p)
                                <li class="py-2">
                                    <a href="{{ route('activities.show', $p->activity) }}" class="link link-primary">
                                        {{ $p->activity->name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('activities.index') }}" class="link link-hover mt-2 inline-block text-sm opacity-80">
                            {{ __('Browse activities') }} →
                        </a>
                    @endif
                </div>

                <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                    <h3 class="mb-3 text-lg font-medium text-base-content">{{ __('My proposals') }}</h3>
                    @if ($myProposals->isEmpty())
                        <p class="text-sm opacity-70">{{ __('No proposals yet.') }}</p>
                    @else
                        <ul class="divide-y divide-base-300">
                            @foreach ($myProposals as $proposal)
                                <li class="py-2">
                                    <span class="font-medium">{{ $proposal->activity->name }}</span>
                                    <span class="text-sm opacity-70">
                                        → {{ $proposal->event->name }}
                                        ({{ ucfirst($proposal->status) }})
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('activity-proposals.index') }}" class="link link-hover mt-2 inline-block text-sm opacity-80">
                            {{ __('All proposals') }} →
                        </a>
                    @endif
                </div>

                <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                    <h3 class="mb-3 text-lg font-medium text-base-content">{{ __('Wishlist – events') }}</h3>
                    @if ($wishlistEvents->isEmpty())
                        <p class="text-sm opacity-70">{{ __('No events in wishlist.') }}</p>
                        <a href="{{ route('browse.events') }}" class="link link-primary mt-2 inline-block text-sm">{{ __('Browse events') }} →</a>
                    @else
                        <ul class="divide-y divide-base-300">
                            @foreach ($wishlistEvents as $event)
                                <li class="py-2 flex items-center justify-between">
                                    <a href="{{ route('events.show', $event) }}" class="link link-primary">{{ $event->name }}</a>
                                    <form action="{{ route('wishlist.events.remove', $event) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-xs">{{ __('Remove') }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('browse.events') }}" class="link link-hover mt-2 inline-block text-sm opacity-80">{{ __('Browse events') }} →</a>
                    @endif
                </div>

                <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                    <h3 class="mb-3 text-lg font-medium text-base-content">{{ __('Wishlist – activities') }}</h3>
                    @if ($wishlistActivities->isEmpty())
                        <p class="text-sm opacity-70">{{ __('No activities in wishlist.') }}</p>
                        <a href="{{ route('browse.activities') }}" class="link link-primary mt-2 inline-block text-sm">{{ __('Browse activities') }} →</a>
                    @else
                        <ul class="divide-y divide-base-300">
                            @foreach ($wishlistActivities as $activity)
                                <li class="py-2 flex items-center justify-between">
                                    <a href="{{ route('activities.show', $activity) }}" class="link link-primary">{{ $activity->name }}</a>
                                    <form action="{{ route('wishlist.activities.remove', $activity) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-xs">{{ __('Remove') }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('browse.activities') }}" class="link link-hover mt-2 inline-block text-sm opacity-80">{{ __('Browse activities') }} →</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
