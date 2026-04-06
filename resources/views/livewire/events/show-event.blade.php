<div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div role="alert" class="alert alert-success text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="alert alert-error text-sm">{{ $errors->first() }}</div>
            @endif

            @php
                $eventPlaces = $event->places
                    ->filter(fn ($place) => $place
                        && $place->type === 'venue'
                        && $place->latitude !== null
                        && $place->longitude !== null)
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
                                <x-button
                                    type="button"
                                    x-data="{ copied: false }"
                                    x-on:click="navigator.clipboard.writeText('{{ url()->current() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="pointer-events-auto btn-ghost btn-xs mt-1 text-white"
                                    x-bind:title="copied ? @js(__('ui.events.copied')) : @js(__('ui.events.copy_link'))"
                                >
                                    <span x-show="!copied">{{ __('ui.events.share') }}</span>
                                    <span x-show="copied" x-cloak>{{ __('ui.events.link_copied') }}</span>
                                </x-button>
                            </div>
                        </div>
                    </div>
                </div>
                @auth
                    @if ($canManageEvent)
                        <div class="flex flex-wrap items-center justify-end gap-1 border-b border-base-300 bg-base-100 px-3 py-2">
                            <x-button
                                :link="route('events.edit', $event)"
                                class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                :title="__('Edit')"
                                :aria-label="__('Edit').': '.$event->name"
                                data-ui="event-show-edit-open"
                            >
                                <x-ui.icons.pencil class="h-5 w-5 shrink-0" />
                            </x-button>
                            <x-button
                                type="button"
                                class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                wire:click="deleteEvent"
                                wire:confirm="{{ __('Are you sure you want to delete this event?') }}"
                                :title="__('Delete')"
                                :aria-label="__('Delete').': '.$event->name"
                                data-ui="event-show-delete"
                            >
                                <x-ui.icons.trash class="h-5 w-5 shrink-0" />
                            </x-button>
                        </div>
                    @endif
                @endauth
                @php
                    $hasEventDescription = filled(rich_text_excerpt($event->description));
                    $hasEnrollmentWindows = $event->enrollmentWindows->isNotEmpty();
                @endphp
                <div class="space-y-6 p-6">
                    @if ($hasEventDescription)
                        <div class="rich-text-content text-sm text-base-content/80">
                            {!! rich_text($event->description) !!}
                        </div>
                    @endif

                    @if ($hasEnrollmentWindows)
                        <div @class(['border-t border-base-300 pt-6' => $hasEventDescription])>
                            <h3 class="text-base font-semibold text-base-content">{{ __('ui.events.enrollment_windows_heading') }}</h3>
                            @if ($activeEnrollmentWindow)
                                <div
                                    role="status"
                                    class="mt-3 flex items-center gap-2 rounded-lg border border-success/40 bg-success/10 px-3 py-2 text-sm text-success"
                                    data-ui="event-show-enrollment-open"
                                >
                                    <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-success" aria-hidden="true"></span>
                                    <span>{{ __('ui.events.enrollment_open_now') }}</span>
                                </div>
                            @endif
                            <ul class="mt-3 space-y-3 text-sm text-base-content/90">
                                @foreach ($event->enrollmentWindows as $window)
                                    @php
                                        $isThisWindowActive = $activeEnrollmentWindow && $activeEnrollmentWindow->is($window);
                                        $maxLabel = $window->maxActivitiesPerUserEffective();
                                    @endphp
                                    <li
                                        @class([
                                            'rounded-lg border px-3 py-2',
                                            'border-success/50 bg-success/5' => $isThisWindowActive,
                                            'border-base-300 bg-base-200/40' => ! $isThisWindowActive,
                                        ])
                                        data-ui="event-show-enrollment-window"
                                    >
                                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                            <span class="font-medium tabular-nums text-base-content">
                                                {{ format_datetime_in_user_tz($window->starts_at, 'ddd, D MMM · HH:mm') }}
                                                <span class="text-base-content/50">–</span>
                                                {{ format_datetime_in_user_tz($window->ends_at, 'ddd, D MMM · HH:mm') }}
                                            </span>
                                            @if ($isThisWindowActive)
                                                <span class="badge badge-success badge-sm shrink-0">{{ __('ui.events.enrollment_window_active_badge') }}</span>
                                            @endif
                                        </div>
                                        @if ($maxLabel !== null)
                                            <p class="mt-1 text-xs text-base-content/70">
                                                {{ __('ui.events.enrollment_window_max_activities') }}:
                                                <span class="tabular-nums font-medium text-base-content/90">{{ $maxLabel }}</span>
                                            </p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            <div id="ui-event-show-slots" class="ui-event-show-slots rounded-lg border border-base-300 bg-base-100 p-6 shadow" data-ui="event-show-slots">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-medium text-base-content">{{ __('ui.events.event_plan') }}</h3>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @auth
                            @php
                                $proposeActivityUrl = ! empty($proposalSlotIds)
                                    ? route('activities.create').'?'.http_build_query([
                                        'proposal_event_id' => $event->id,
                                        'proposal_slot_ids' => array_map('intval', $proposalSlotIds),
                                    ])
                                    : route('activities.create', ['proposal_event_id' => $event->id]);
                            @endphp
                            <x-button id="ui-event-show-propose" :link="$proposeActivityUrl" class="btn-primary btn-sm ui-action ui-action-propose" data-ui="event-show-propose" wire:navigate>
                                {{ __('ui.events.propose_activity') }}
                            </x-button>
                            @if ($canManageEvent)
                                <x-button
                                    id="ui-event-show-create-slots"
                                    type="button"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary ui-action ui-action-create-slots"
                                    onclick="document.getElementById('event-slots-create-modal')?.showModal()"
                                    :title="__('ui.slots.create_slots')"
                                    :aria-label="__('ui.slots.create_slots')"
                                    data-ui="event-show-create-slots"
                                >
                                    <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </x-button>
                            @endif
                        @endauth
                    </div>
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
                            @if ($group['slots']->isNotEmpty())
                            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($group['slots'] as $slot)
                                    @php
                                        $activity = $slot->activity;
                                        $participantsCount = $activity
                                            ? $activity->physicalHeadcountForSlotCapacity()
                                            : (filled($slot->max_capacity) ? $slot->max_capacity : null);
                                        if ($activity) {
                                            $mergedActivitySlotTags = $activity->tags
                                                ->filter(fn ($t) => in_array($t->category, $slotListActivityTagCategories, true))
                                                ->sortBy(fn ($t) => $tagCategoryOrder[$t->category] ?? 100);
                                        }
                                    @endphp
                                    <li
                                        @class([
                                            'group relative rounded-lg border border-base-300 bg-base-100/50 p-4',
                                            'transition hover:border-base-content/20' => $activity,
                                            'cursor-pointer' => auth()->check() && ! $activity,
                                            'ring-2 ring-primary/50 bg-primary/5' => auth()->check() && ! $activity && in_array($slot->id, $proposalSlotIds, true),
                                        ])
                                        @if (auth()->check() && ! $activity)
                                            wire:click="toggleProposalSlot({{ $slot->id }})"
                                        @endif
                                    >
                                        @if ($activity)
                                            <a
                                                href="{{ route('activities.show', $activity) }}"
                                                wire:navigate
                                                class="absolute inset-0 z-[1] block cursor-pointer rounded-lg ring-inset ring-primary/0 transition group-hover:ring-2 group-hover:ring-primary/15"
                                                aria-label="{{ $activity->name }}"
                                            >
                                            </a>
                                        @endif
                                        <div @class(['relative z-[2] flex items-start justify-between gap-2', 'pointer-events-none' => $activity])>
                                            <div class="min-w-0 flex-1 space-y-1.5">
                                                @if ($activity)
                                                    <h4 class="text-base font-semibold leading-snug text-base-content">{{ $activity->name }}</h4>
                                                @endif
                                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
                                                    <span @class(['font-medium text-base-content' => ! $activity, 'font-medium text-base-content/80' => $activity])>{{ $slot->name }}</span>
                                                    @if ($slot->starts_at || $slot->ends_at)
                                                        <span class="inline-flex items-center gap-1.5 tabular-nums text-base-content/70">
                                                            <svg class="h-4 w-4 shrink-0 text-base-content/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                            </svg>
                                                            <span>
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
                                                    @if ($participantsCount !== null)
                                                        <span class="inline-flex shrink-0 items-center gap-1.5 tabular-nums text-base-content/60" title="{{ $participantsCount }}" aria-label="{{ $participantsCount }}">
                                                            <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                                            </svg>
                                                            <span>{{ $participantsCount }}</span>
                                                        </span>
                                                    @endif
                                                </div>
                                                @if ($slot->place)
                                                    <p class="text-sm text-base-content/70">{{ $slot->place->venueRoomLabel() }}</p>
                                                @endif
                                                @if ($activity)
                                                    @if ($mergedActivitySlotTags->isNotEmpty() || filled($activity->minimum_age) || filled($activity->type))
                                                        <div class="mt-1 flex flex-wrap gap-1">
                                                            @if (filled($activity->minimum_age))
                                                                <span class="badge badge-primary badge-outline tabular-nums">{{ $activity->minimum_age }}+</span>
                                                            @endif
                                                            @if (filled($activity->type))
                                                                <span class="badge badge-outline badge-info capitalize">{{ $activity->type->value }}</span>
                                                            @endif
                                                            @foreach ($mergedActivitySlotTags as $tag)
                                                                <span class="badge badge-primary badge-outline whitespace-normal text-left">
                                                                    {{ $tag->translations->firstWhere('locale', app()->getLocale())?->label ?? ($tag->translations->firstWhere('locale', app()->getLocale())?->slug ?? '#'.$tag->id) }}
                                                                </span>
                                                            @endforeach
                                                        </div>
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
                                                @endif
                                            </div>
                                            @auth
                                                @php
                                                    $showDetachActivity = $canManageEvent && $activity;
                                                    $showSlotEditDelete = auth()->user()?->canModifyEntity($slot) ?? false;
                                                @endphp
                                                @if ($showDetachActivity || $showSlotEditDelete)
                                                    <div class="relative z-[3] flex shrink-0 gap-0.5 pointer-events-auto" @if (! $activity) onclick="event.stopPropagation()" @endif>
                                                        @if ($showDetachActivity)
                                                            <x-button
                                                                type="button"
                                                                class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning"
                                                                :title="__('ui.events.detach_activity_from_slot')"
                                                                :aria-label="__('ui.events.detach_activity_from_slot')"
                                                                wire:click="detachActivityFromSlot({{ $slot->id }})"
                                                                wire:confirm="{{ __('ui.events.detach_activity_from_slot_confirm') }}"
                                                            >
                                                                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622.621-.621A4.5 4.5 0 0 0 12.182 3.182l-4.5 4.5a4.5 4.5 0 0 0 0 6.364l1.757 1.757" />
                                                                </svg>
                                                            </x-button>
                                                        @endif
                                                        @if ($showSlotEditDelete)
                                                            <x-button
                                                                type="button"
                                                                class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                                                :title="__('ui.events.edit_slot')"
                                                                :aria-label="__('ui.events.edit_slot')"
                                                                onclick="window.openSlotEditModal?.({{ $slot->id }})"
                                                            >
                                                                <x-ui.icons.pencil class="h-5 w-5 shrink-0" />
                                                            </x-button>
                                                            <x-button
                                                                type="button"
                                                                class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                                                :title="__('Delete')"
                                                                :aria-label="__('Delete')"
                                                                wire:click="deleteSlot({{ $slot->id }})"
                                                                wire:confirm="{{ __('Are you sure?') }}"
                                                            >
                                                                <x-ui.icons.trash class="h-5 w-5 shrink-0" />
                                                            </x-button>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endauth
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            @elseif (! empty($group['boundary']))
                            <ul class="grid grid-cols-1 gap-3">
                                <li class="rounded-lg border border-base-300 bg-base-100/50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                        @if ($group['boundary'] === 'event_start')
                                            {{ __('ui.events.event_boundary_starts') }}
                                        @else
                                            {{ __('ui.events.event_boundary_ends') }}
                                        @endif
                                    </p>
                                </li>
                            </ul>
                            @endif
                        </li>
                    @empty
                        <li class="py-2 text-sm text-base-content/70">{{ __('ui.events.no_slots_yet') }}</li>
                    @endforelse
                </ul>
            </div>

            @auth
                @if ($canManageEvent)
                    <dialog id="event-slots-create-modal" class="modal">
                        <div class="modal-box max-w-3xl">
                            @include('slots.mass-create', [
                                'lockedEvent' => $event,
                                'events' => collect([$event]),
                                'slotNameSuggestions' => $slotNameSuggestions,
                                'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
                                'slotMassVenues' => $slotMassVenues,
                                'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
                                'embeddedInModal' => true,
                                'massFormAction' => route('events.slots.mass', $event),
                            ])
                        </div>
                        <form method="dialog" class="modal-backdrop">
                            <x-button type="submit" class="btn-ghost">{{ __('ui.common.cancel') }}</x-button>
                        </form>
                    </dialog>
                @endif
            @endauth

            @if ($canManageEvent && $pendingProposals->isNotEmpty())
                <div id="ui-event-show-pending-proposals" class="ui-event-show-pending-proposals rounded-lg border border-warning/40 bg-base-100 p-6 shadow" data-ui="event-show-pending-proposals">
                    <h3 class="mb-1 text-lg font-medium text-base-content">{{ __('ui.events.pending_proposals') }}</h3>
                    <p class="mb-3 text-sm text-base-content/80">{{ __('ui.events.pending_proposals_help') }}</p>
                    <ul class="divide-y divide-base-300">
                        @foreach ($pendingProposals as $proposal)
                            @php
                                $pa = $proposal->activity;
                                $gameTags = $pa->tags->filter(fn ($t) => $t->category === 'game')->values();
                                $durationLabel = format_activity_duration_compact($pa->duration_in_minutes);
                            @endphp
                            <li class="py-3 flex flex-wrap items-center justify-between gap-2">
                                <div class="space-y-1.5 min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                        <span class="badge badge-outline badge-info capitalize shrink-0">{{ $pa->type->value }}</span>
                                        <a href="{{ route('activities.show', $pa) }}" class="link link-primary min-w-0 font-medium break-words">
                                            {{ $pa->name }}
                                        </a>
                                        <span class="text-sm text-base-content/70"> · {{ __('ui.common.by') }} {{ $proposal->creator->nickname ?? $proposal->creator->email }}</span>
                                    </div>
                                    @if ($gameTags->isNotEmpty() || filled($pa->minimum_age) || $durationLabel !== null || filled($pa->max_participants))
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
                                            @foreach ($gameTags as $tag)
                                                <span class="badge badge-primary badge-outline whitespace-normal text-left">
                                                    {{ $tag->translations->firstWhere('locale', app()->getLocale())?->label ?? ($tag->translations->firstWhere('locale', app()->getLocale())?->slug ?? '#'.$tag->id) }}
                                                </span>
                                            @endforeach
                                            @if (filled($pa->minimum_age))
                                                <span class="badge badge-primary badge-outline tabular-nums">{{ $pa->minimum_age }}+</span>
                                            @endif
                                            @if ($durationLabel !== null)
                                                <span class="inline-flex shrink-0 items-center gap-1.5 text-base-content/70" title="{{ __('ui.activities.show_duration') }}">
                                                    <svg class="h-4 w-4 shrink-0 text-base-content/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                    <span class="tabular-nums">{{ $durationLabel }}</span>
                                                </span>
                                            @endif
                                            @if (filled($pa->max_participants))
                                                <span class="inline-flex shrink-0 items-center gap-1.5 tabular-nums text-base-content/60" title="{{ __('ui.activities.max_participants') }}">
                                                    <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                                    </svg>
                                                    <span>{{ $pa->max_participants }}</span>
                                                </span>
                                            @endif
                                        </div>
                                    @endif
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
                                @php
                                    $freeSlotsAll = $event->slots->whereNull('activity_id')->values();
                                    $freeSlots = $freeSlotsAll->filter(fn ($s) => $s->fitsProposalActivity($pa))->values();
                                @endphp
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($freeSlotsAll->isEmpty())
                                        <span class="text-sm text-base-content/50">{{ __('ui.events.no_free_slots') }}</span>
                                    @elseif ($freeSlots->isEmpty())
                                        <span class="text-sm text-base-content/50">{{ __('ui.events.no_compatible_slots') }}</span>
                                    @else
                                        @php
                                            $acceptSlotSelectOptions = $freeSlots->map(fn ($s) => [
                                                'id' => $s->id,
                                                'name' => $s->proposalAcceptOptionLabel(),
                                            ])->values()->all();
                                        @endphp
                                        <div class="inline-flex max-w-full flex-wrap items-center gap-1" wire:key="proposal-accept-{{ $proposal->id }}">
                                            <x-select
                                                wire:model.live="proposalAcceptSlotId.{{ $proposal->id }}"
                                                :options="$acceptSlotSelectOptions"
                                                :placeholder="__('ui.events.choose_slot_or_auto')"
                                                placeholder-value=""
                                                class="select-sm max-w-md min-w-[12rem] flex-1"
                                                :error-field="'proposalAcceptSlot.'.$proposal->id"
                                            />
                                            <x-button
                                                type="button"
                                                class="btn-success btn-square btn-sm shrink-0"
                                                :title="__('ui.events.accept')"
                                                wire:click="acceptPendingProposal({{ $proposal->id }})"
                                                wire:loading.attr="disabled"
                                            >
                                                <span class="sr-only">{{ __('ui.events.accept') }}</span>
                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                            </x-button>
                                        </div>
                                    @endif
                                    <x-button
                                        type="button"
                                        class="btn-error btn-square btn-sm shrink-0"
                                        :title="__('ui.events.reject')"
                                        wire:click="rejectPendingProposal({{ $proposal->id }})"
                                        wire:loading.attr="disabled"
                                    >
                                        <span class="sr-only">{{ __('ui.events.reject') }}</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </x-button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('slots.partials.edit-modal-shell')

            <div class="flex gap-3">
                <x-button :link="route('search.index')" class="btn-ghost btn-sm">{{ __('ui.events.back_to_search') }}</x-button>
            </div>
        </div>
    </div>
