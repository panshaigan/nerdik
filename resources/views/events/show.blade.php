<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div role="alert" class="alert alert-success text-sm">{{ session('status') }}</div>
            @endif

            @php
                $eventPlaces = $event->places
                    ->filter(fn ($place) => $place && $place->latitude !== null && $place->longitude !== null)
                    ->unique('id')
                    ->values();
                $eventPlacesMapConfig = [
                    'places' => $eventPlaces->map(fn ($place) => [
                        'name' => (string) $place->name,
                        'lat' => (float) $place->latitude,
                        'lng' => (float) $place->longitude,
                    ])->all(),
                ];
                $eventPlaceNames = $eventPlaces->pluck('name')->filter()->implode(', ');
                $eventDateSummary = format_date_range_compact($event->starts_at, $event->ends_at);
            @endphp

            <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow">
                <div class="relative isolate" data-event-show-map-root>
                    <script type="application/json" data-event-show-map-config>@json($eventPlacesMapConfig)</script>
                    <div
                        data-event-show-map
                        class="relative z-0 w-full bg-base-200/30"
                        style="min-height: 260px; height: min(420px, 50vh);"
                    ></div>
                    <div class="absolute inset-x-0 bottom-0 z-50 bg-black/55 px-4 py-3 text-white backdrop-blur-[1px]">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium">{{ $eventPlaceNames !== '' ? $eventPlaceNames : __('No mapped places yet') }}</p>
                                <p class="text-xs text-white/90">{{ $eventDateSummary }}</p>
                            </div>
                            <div class="text-right">
                                @if ($event->hostDisplayName())
                                    <p class="text-xs text-white/90">{{ __('Organized by') }} {{ $event->hostDisplayName() }}</p>
                                @endif
                                <button
                                    type="button"
                                    x-data="{ copied: false }"
                                    x-on:click="navigator.clipboard.writeText('{{ url()->current() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="pointer-events-auto btn btn-ghost btn-xs mt-1 text-white"
                                    :title="copied ? '{{ __('ui.events.copied') }}' : '{{ __('ui.events.copy_link') }}'"
                                >
                                    <span x-show="!copied">{{ __('ui.events.share') }}</span>
                                    <span x-show="copied" x-cloak>{{ __('ui.events.link_copied') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                @if ($event->tags->isNotEmpty())
                    <div>
                        @include('tags.partials.inline', ['tags' => $event->tags, 'class' => ''])
                    </div>
                @endif
                @if ($event->desc)
                    <p class="mt-4 text-sm text-base-content/80">{{ $event->desc }}</p>
                @endif
                @php
                    $canManageEvent = auth()->check() && ($event->created_by === auth()->id() || (auth()->user()->is_admin ?? false));
                @endphp
                <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                    @auth
                        @if ($canManageEvent)
                            <a href="{{ route('events.edit', $event) }}" class="btn btn-outline btn-sm">
                                {{ __('ui.events.edit') }}
                            </a>
                        @endif
                        <a href="{{ route('events.propose', $event) }}" class="btn btn-primary btn-sm">
                            {{ __('ui.events.propose_activity') }}
                        </a>
                    @endauth
                </div>
                </div>
            </div>

            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <h3 class="text-lg font-medium text-base-content">{{ __('ui.events.slots') }}</h3>
                    @auth
                        @if ($event->created_by === auth()->id())
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('event-slots-create-modal')?.showModal()">
                                    {{ __('ui.slots.create_slots') }}
                                </button>
                            </div>
                        @endif
                    @endauth
                </div>
                <ul class="divide-y divide-base-300">
                    @forelse ($event->slots as $slot)
                        <li class="py-3 flex items-center justify-between">
                            <div>
                                <span class="font-medium">{{ $slot->name }}</span>
                                @if ($slot->starts_at)
                                    <span class="text-base-content/70 text-sm"> · {{ format_in_user_tz($slot->starts_at, 'H:i') }}</span>
                                @endif
                                @if ($slot->place)
                                    <span class="text-base-content/70 text-sm"> · {{ $slot->place->name }}</span>
                                @endif
                                @if ($slot->activity)
                                    <span class="text-sm text-primary">
                                        → <a href="{{ route('activities.show', $slot->activity) }}" class="hover:underline">{{ $slot->activity->name }}</a>
                                    </span>
                                @else
                                    <span class="text-sm text-base-content/50">— {{ __('ui.events.free') }}</span>
                                @endif
                            </div>
                            @auth
                                @if ($slot->created_by === auth()->id() || (auth()->user()->is_admin ?? false))
                                    <a href="{{ route('slots.edit', $slot) }}" class="btn btn-ghost btn-xs">{{ __('ui.events.edit_slot') }}</a>
                                @endif
                            @endauth
                        </li>
                    @empty
                        <li class="py-2 text-sm text-base-content/70">{{ __('ui.events.no_slots_yet') }}</li>
                    @endforelse
                </ul>
            </div>

            @auth
                @if ($event->created_by === auth()->id())
                    <dialog id="event-slots-create-modal" class="modal">
                        <div class="modal-box max-w-3xl">
                            <h3 class="text-lg font-semibold text-base-content">{{ __('ui.slots.create_slots') }}</h3>
                            <div class="mt-4">
                                @include('slots.mass-create', [
                                    'lockedEvent' => $event,
                                    'events' => collect([$event]),
                                    'places' => $places,
                                    'embeddedInModal' => true,
                                ])
                            </div>
                        </div>
                        <form method="dialog" class="modal-backdrop">
                            <button>{{ __('ui.common.cancel') }}</button>
                        </form>
                    </dialog>
                @endif
            @endauth

            @if ($isOwner && $pendingProposals->isNotEmpty())
                <div class="rounded-lg border border-warning/40 bg-base-100 p-6 shadow">
                    <h3 class="mb-1 text-lg font-medium text-base-content">{{ __('ui.events.pending_proposals') }}</h3>
                    <p class="mb-3 text-sm text-base-content/80">{{ __('ui.events.pending_proposals_help') }}</p>
                    <ul class="divide-y divide-base-300">
                        @foreach ($pendingProposals as $proposal)
                            <li class="py-3 flex flex-wrap items-center justify-between gap-2">
                                <div class="space-y-1 min-w-0 flex-1">
                                    <div>
                                        <a href="{{ route('activities.show', $proposal->activity) }}" class="font-medium text-primary hover:underline">
                                            {{ $proposal->activity->name }}
                                        </a>
                                        <span class="text-sm text-base-content/70"> · {{ __('ui.common.by') }} {{ $proposal->creator->nickname ?? $proposal->creator->email }}</span>
                                    </div>
                                    @if ($proposal->proposedSlots->isNotEmpty())
                                        <p class="text-sm text-base-content/80">
                                            <span class="font-medium text-base-content">{{ __('ui.events.preferred_slots') }}:</span>
                                            {{ $proposal->proposedSlots->pluck('name')->join(', ') }}
                                        </p>
                                    @endif
                                    @if ($proposal->preferred_start_time)
                                        <p class="text-sm text-base-content/80">
                                            <span class="font-medium text-base-content">{{ __('ui.events.preferred_time') }}:</span>
                                            {{ format_in_user_tz($proposal->preferred_start_time) }}
                                        </p>
                                    @endif
                                </div>
                                @php $freeSlots = $event->slots->where('activity_id', null); @endphp
                                <div class="flex flex-wrap gap-2 items-center">
                                    @if ($freeSlots->isNotEmpty())
                                        <form action="{{ route('activity-proposals.accept', $proposal) }}" method="POST" class="inline flex items-center gap-1">
                                            @csrf
                                            <select name="slot_id" required class="select select-bordered select-sm">
                                                <option value="">{{ __('ui.events.choose_slot') }}</option>
                                                @foreach ($freeSlots as $s)
                                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-success btn-xs">{{ __('ui.events.accept') }}</button>
                                        </form>
                                    @else
                                        <span class="text-sm text-base-content/50">{{ __('ui.events.no_free_slots') }}</span>
                                    @endif
                                    <form action="{{ route('activity-proposals.reject', $proposal) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="btn btn-error btn-xs">{{ __('ui.events.reject') }}</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex gap-3">
                <a href="{{ route('events.index') }}" class="btn btn-ghost btn-sm">{{ __('ui.events.back_to_events') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>
