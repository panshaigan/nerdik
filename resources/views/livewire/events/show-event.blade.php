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

            <div id="ui-event-show-hero" class="ui-event-show-hero overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow" data-ui="event-show-hero">
                <div class="relative isolate" data-event-show-map-root wire:ignore>
                    <script type="application/json" data-event-show-map-config>@json($eventPlacesMapConfig)</script>
                    <div
                        id="ui-event-show-map"
                        data-event-show-map
                        class="relative z-0 w-full bg-base-200/30"
                        style="min-height: 260px; height: min(420px, 50vh);"
                        data-ui="event-show-map"
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
                            <x-button :link="route('events.edit', $event)" class="btn-outline btn-sm">
                                {{ __('ui.events.edit') }}
                            </x-button>
                        @endif
                        <x-button id="ui-event-show-propose" :link="route('events.propose', $event)" class="btn-primary btn-sm ui-action ui-action-propose" data-ui="event-show-propose">
                            {{ __('ui.events.propose_activity') }}
                        </x-button>
                    @endauth
                </div>
                </div>
            </div>

            <div id="ui-event-show-slots" class="ui-event-show-slots rounded-lg border border-base-300 bg-base-100 p-6 shadow" data-ui="event-show-slots">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <h3 class="text-lg font-medium text-base-content">{{ __('ui.events.slots') }}</h3>
                    @auth
                        @if ($event->created_by === auth()->id())
                            <div class="flex flex-wrap gap-2">
                                <x-button id="ui-event-show-create-slots" type="button" class="btn-outline btn-sm ui-action ui-action-create-slots" onclick="document.getElementById('event-slots-create-modal')?.showModal()" data-ui="event-show-create-slots">
                                    {{ __('ui.slots.create_slots') }}
                                </x-button>
                            </div>
                        @endif
                    @endauth
                </div>
                @php
                    $tagCategoryOrder = array_flip($slotListActivityTagCategories);
                @endphp
                <ul class="space-y-6">
                    @forelse ($slotHourGroups as $group)
                        <li class="list-none">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                {{ $group['label'] }}
                            </p>
                            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach ($group['slots'] as $slot)
                                    <li class="flex flex-col gap-3 rounded-lg border border-base-300 bg-base-100/50 p-4 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0 flex-1 space-y-1.5">
                                            @if ($slot->activity)
                                                <div class="mb-1 text-sm">
                                                    <a href="{{ route('activities.show', $slot->activity) }}" class="link link-primary">
                                                        {{ $slot->activity->name }}
                                                    </a>
                                                </div>
                                            @endif
                                            <div class="space-y-0.5">
                                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1.5">
                                                    <span class="font-medium text-base-content">{{ $slot->name }}</span>
                                                    @if ($slot->starts_at || $slot->ends_at)
                                                        <span class="text-sm text-base-content/70">
                                                            <span class="whitespace-pre"> · </span>
                                                            <span class="tabular-nums">
                                                                @if ($slot->starts_at && $slot->ends_at)
                                                                    {{ format_in_user_tz($slot->starts_at, 'H:i') }}<span class="text-base-content/50"> – </span>{{ format_in_user_tz($slot->ends_at, 'H:i') }}
                                                                @elseif ($slot->starts_at)
                                                                    {{ format_in_user_tz($slot->starts_at, 'H:i') }}
                                                                @else
                                                                    {{ format_in_user_tz($slot->ends_at, 'H:i') }}
                                                                @endif
                                                            </span>
                                                        </span>
                                                    @endif
                                                </div>
                                                @if ($slot->place)
                                                    <p class="text-sm text-base-content/70">
                                                        {{ $slot->place->venueRoomLabel() }}
                                                    </p>
                                                @endif
                                            </div>
                                            @if ($slot->activity)
                                                @php
                                                    $listTags = $slot->activity->tags
                                                        ->filter(fn ($t) => in_array($t->category, $slotListActivityTagCategories, true))
                                                        ->sortBy(fn ($t) => $tagCategoryOrder[$t->category] ?? 100);
                                                @endphp
                                                @if ($listTags->isNotEmpty())
                                                    @include('tags.partials.inline', ['tags' => $listTags, 'class' => 'mt-1'])
                                                @endif
                                            @else
                                                @php
                                                    $slotActivityTypes = collect($slot->activity_types)
                                                        ->filter(fn ($t) => is_string($t) && trim($t) !== '')
                                                        ->map(fn ($t) => trim($t))
                                                        ->unique()
                                                        ->values();
                                                @endphp
                                                @if ($slotActivityTypes->isNotEmpty())
                                                    <div class="my-2 flex flex-wrap gap-1">
                                                        @foreach ($slotActivityTypes as $type)
                                                            <span class="badge badge-outline badge-info capitalize">{{ $type }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @php
                                                    $listTags = $slot->tags
                                                        ->filter(fn ($t) => in_array($t->category, $slotListActivityTagCategories, true))
                                                        ->sortBy(fn ($t) => $tagCategoryOrder[$t->category] ?? 100);
                                                @endphp
                                                @if ($listTags->isNotEmpty())
                                                    @include('tags.partials.inline', ['tags' => $listTags, 'class' => 'my-2'])
                                                @endif
                                            @endif
                                        </div>
                                        @auth
                                            @if ($slot->created_by === auth()->id() || (auth()->user()->is_admin ?? false))
                                                <div class="flex shrink-0 items-center gap-1 self-start sm:self-center">
                                                    <x-button
                                                        type="button"
                                                        class="btn-ghost btn-xs btn-square"
                                                        title="{{ __('ui.events.edit_slot') }}"
                                                        aria-label="{{ __('ui.events.edit_slot') }}"
                                                        onclick="window.openSlotEditModal?.({{ $slot->id }})"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                            <path d="M17.414 2.586a2 2 0 010 2.828l-9.5 9.5a1 1 0 01-.454.263l-4 1a1 1 0 01-1.212-1.212l1-4a1 1 0 01.263-.454l9.5-9.5a2 2 0 012.828 0zM6.207 11.379l-.5 2 2-.5 8.293-8.293-1.5-1.5-8.293 8.293z"/>
                                                        </svg>
                                                    </x-button>
                                                    <form action="{{ route('slots.destroy', $slot) }}" method="POST" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-button
                                                            type="submit"
                                                            class="btn-ghost btn-xs btn-square text-error"
                                                            title="{{ __('Delete') }}"
                                                            aria-label="{{ __('Delete') }}"
                                                            onclick="return confirm('{{ __('Are you sure?') }}')"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M8.5 2A1.5 1.5 0 007 3.5V4H4.5a.5.5 0 000 1h.538l.853 10.236A2 2 0 007.884 17h4.232a2 2 0 001.993-1.764L14.962 5h.538a.5.5 0 000-1H13v-.5A1.5 1.5 0 0011.5 2h-3zM12 4v-.5a.5.5 0 00-.5-.5h-3a.5.5 0 00-.5.5V4h4zm-4.5 3a.5.5 0 011 0v7a.5.5 0 11-1 0V7zm4 0a.5.5 0 10-1 0v7a.5.5 0 101 0V7z" clip-rule="evenodd"/>
                                                            </svg>
                                                        </x-button>
                                                    </form>
                                                </div>
                                            @endif
                                        @endauth
                                    </li>
                                @endforeach
                            </ul>
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
                            @include('slots.mass-create', [
                                'lockedEvent' => $event,
                                'events' => collect([$event]),
                                'tags' => $slotFormTags,
                                'slotNameSuggestions' => $slotNameSuggestions,
                                'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
                                'slotMassVenues' => $slotMassVenues,
                                'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
                                'embeddedInModal' => true,
                            ])
                        </div>
                        <form method="dialog" class="modal-backdrop">
                            <button>{{ __('ui.common.cancel') }}</button>
                        </form>
                    </dialog>
                @endif
            @endauth

            @if ($isOwner && $pendingProposals->isNotEmpty())
                <div id="ui-event-show-pending-proposals" class="ui-event-show-pending-proposals rounded-lg border border-warning/40 bg-base-100 p-6 shadow" data-ui="event-show-pending-proposals">
                    <h3 class="mb-1 text-lg font-medium text-base-content">{{ __('ui.events.pending_proposals') }}</h3>
                    <p class="mb-3 text-sm text-base-content/80">{{ __('ui.events.pending_proposals_help') }}</p>
                    <ul class="divide-y divide-base-300">
                        @foreach ($pendingProposals as $proposal)
                            <li class="py-3 flex flex-wrap items-center justify-between gap-2">
                                <div class="space-y-1 min-w-0 flex-1">
                                    <div>
                                        <a href="{{ route('activities.show', $proposal->activity) }}" class="link link-primary font-medium">
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
                                        <form action="{{ route('activity-proposals.accept', $proposal) }}" method="POST" class="inline flex items-end gap-1">
                                            @csrf
                                            <x-form-select name="slot_id" required class="select-sm" :omit-error="true">
                                                <option value="">{{ __('ui.events.choose_slot') }}</option>
                                                @foreach ($freeSlots as $s)
                                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                                @endforeach
                                            </x-form-select>
                                            <x-button type="submit" class="btn-success btn-xs">{{ __('ui.events.accept') }}</x-button>
                                        </form>
                                    @else
                                        <span class="text-sm text-base-content/50">{{ __('ui.events.no_free_slots') }}</span>
                                    @endif
                                    <form action="{{ route('activity-proposals.reject', $proposal) }}" method="POST" class="inline">
                                        @csrf
                                        <x-button type="submit" class="btn-error btn-xs">{{ __('ui.events.reject') }}</x-button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('slots.partials.edit-modal-shell')

            <div class="flex gap-3">
                <x-button :link="route('events.index')" class="btn-ghost btn-sm">{{ __('ui.events.back_to_events') }}</x-button>
            </div>
        </div>
    </div>
