<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
        @if (session('status'))
            <div role="alert" class="alert alert-success text-sm">{{ session('status') }}</div>
        @endif

        <section class="space-y-4">
            <div class="rounded-2xl border border-base-300 bg-base-100/90 p-6 shadow-xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
                        <div class="rounded-xl bg-primary/15 p-4 text-center">
                            <p class="text-3xl font-bold text-primary">{{ $myActivities->count() }}</p>
                            <p class="text-xs uppercase tracking-wide opacity-80">Sessions</p>
                        </div>
                        <div class="rounded-xl bg-secondary/15 p-4 text-center">
                            <p class="text-3xl font-bold text-secondary">{{ $participations->count() }}</p>
                            <p class="text-xs uppercase tracking-wide opacity-80">Players</p>
                        </div>
                    </div>
                </div>
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
                    <a href="{{ route('search.index') }}" class="link link-hover mt-2 inline-block text-sm opacity-80">
                        {{ __('ui.nav.search') }} →
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
                                @php
                                    $activityTypeSlug = $activity->activityType?->slug;
                                    $activityTypeLabel = $activityTypeSlug ? __('ui.activities.types.'.$activityTypeSlug) : __('ui.common.none');
                                @endphp
                                <span class="text-sm opacity-70"> · {{ $activityTypeLabel }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('search.index') }}" class="link link-hover mt-2 inline-block text-sm opacity-80">
                        {{ __('ui.nav.search') }} →
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
                    <a href="{{ route('search.index') }}" class="link link-hover mt-2 inline-block text-sm opacity-80">
                        {{ __('ui.nav.search') }} →
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
                                    ({{ ucfirst($proposal->status->value) }})
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
                <h3 class="mb-3 text-lg font-medium text-base-content">{{ __('ui.interests.section_events') }}</h3>
                @if ($interestedEvents->isEmpty())
                    <p class="text-sm opacity-70">{{ __('ui.interests.empty_events') }}</p>
                    <a href="{{ route('search.index') }}" class="link link-primary mt-2 inline-block text-sm">{{ __('ui.nav.search') }} →</a>
                @else
                    <ul class="divide-y divide-base-300">
                        @foreach ($interestedEvents as $event)
                            <li class="py-2 flex items-center justify-between">
                                <a href="{{ route('events.show', $event) }}" class="link link-primary">{{ $event->name }}</a>
                                <form action="{{ route('interests.events.remove', $event) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" class="btn-ghost btn-xs">{{ __('Remove') }}</x-button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('search.index') }}" class="link link-hover mt-2 inline-block text-sm opacity-80">{{ __('ui.nav.search') }} →</a>
                @endif
            </div>

            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                <h3 class="mb-3 text-lg font-medium text-base-content">{{ __('ui.interests.section_activities') }}</h3>
                @if ($interestedActivities->isEmpty())
                    <p class="text-sm opacity-70">{{ __('ui.interests.empty_activities') }}</p>
                    <a href="{{ route('search.index') }}" class="link link-primary mt-2 inline-block text-sm">{{ __('ui.nav.search') }} →</a>
                @else
                    <ul class="divide-y divide-base-300">
                        @foreach ($interestedActivities as $activity)
                            <li class="py-2 flex items-center justify-between">
                                <a href="{{ route('activities.show', $activity) }}" class="link link-primary">{{ $activity->name }}</a>
                                <form action="{{ route('interests.activities.remove', $activity) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" class="btn-ghost btn-xs">{{ __('Remove') }}</x-button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('search.index') }}" class="link link-hover mt-2 inline-block text-sm opacity-80">{{ __('ui.nav.search') }} →</a>
                @endif
            </div>
        </div>
    </div>
</div>
