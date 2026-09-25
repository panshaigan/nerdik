@if ($previewEvent)
    <x-modal
        wire:model="eventPreviewModalOpen"
        :title="$previewEvent->name"
        box-class="ui-modal-surface ui-overlay-shell ui-overlay-sheet"
        class="backdrop-blur modal-bottom md:modal-end"
        separator
        data-ui="overlay-sheet"
    >
        <div
            wire:key="listing-event-preview-{{ $previewEvent->id }}"
            class="space-y-5"
            data-ui="listing-event-preview-modal"
        >

            <div class="space-y-3">
                @if ($previewEvent->creator)
                    <div class="min-w-0 max-w-full" data-ui="listing-event-preview-host">
                        <x-user-badge
                            :user="$previewEvent->creator"
                            :organization="$previewEvent->organization"
                            size="sm"
                            name-class="truncate text-xs font-medium text-base-content"
                            class="max-w-full"
                            :contact-wire-key="'listing-event-preview-'.$previewEvent->id"
                        />
                    </div>
                @endif

                @if ($previewEvent->isCancelled())
                    <span class="badge badge-warning">{{ __('ui.events.cancelled_badge') }}</span>
                @endif

                <dl class="space-y-2.5 text-sm">
                    @if ($previewEventTimeSummary !== '')
                        <div class="flex gap-2">
                            <dt class="sr-only">{{ __('Date') }}</dt>
                            <dd class="flex min-w-0 flex-1 gap-2 text-base-content/75">
                                <x-icon name="o-calendar" class="mt-0.5 h-4 w-4 shrink-0 text-primary/70" />
                                <span class="min-w-0 leading-snug">
                                    {{ $previewEventTimeSummary }}
                                </span>
                            </dd>
                        </div>
                    @endif
                    @if (($previewEventLocationPlaces ?? []) !== [] || $previewEventLocationSummary !== '')
                        <div class="flex gap-2">
                            <dt class="sr-only">{{ __('ui.browse.location_label') }}</dt>
                            <dd class="flex min-w-0 flex-1 gap-2 text-base-content/75">
                                <x-icon name="o-map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-primary/70" />
                                <x-browse.place-search-links
                                    :places="$previewEventLocationPlaces ?? []"
                                    :fallback="$previewEventLocationSummary"
                                />
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if (filled(rich_text_excerpt($previewEvent->description)))
                <div class="rich-text-content text-base-content/90">
                    {!! rich_text($previewEvent->description) !!}
                </div>
            @else
                <p class="text-sm text-base-content/60">{{ __('ui.events.show_no_description') }}</p>
            @endif

            @if ($previewEventBadgeItems !== [])
                <x-ui.activity-badge-group
                    :items="$previewEventBadgeItems"
                    data-ui="listing-event-preview-badge-group"
                />
            @endif
        </div>

        <x-slot:actions>
            <div class="flex flex-wrap items-center justify-end gap-2" data-ui="listing-event-preview-actions">
                @php
                    $previewEventSharePayload = app(\App\Support\Sharing\ShareLinks::class)->forEvent($previewEvent);
                    $previewEventCalendarPayload = app(\App\Support\Calendar\CalendarLinks::class)->forEvent($previewEvent);
                    $previewEventProposeReturnPath = route('events.show', ['event' => $previewEvent, 'tab' => 'plan'], false);
                    $previewEventProposeUrl = url_with_return(
                        route('activities.create', ['proposal_event_id' => $previewEvent->id]),
                        $previewEventProposeReturnPath,
                    );
                    $previewEventGuestProposeUrl = login_url($previewEventProposeReturnPath);
                @endphp
                @if ($previewEventSharePayload)
                    <x-ui.share-menu :payload="$previewEventSharePayload" open-upward />
                @endif
                @if ($previewEventCalendarPayload)
                    <x-ui.calendar-menu :payload="$previewEventCalendarPayload" open-upward />
                @endif
                <x-button
                    :link="route('events.show', $previewEvent)"
                    class="btn-outline btn-sm sm:btn-md"
                    wire:navigate
                >
                    {{ __('ui.events.show_details') }}
                </x-button>
                @if ($previewEventCanProposeActivity ?? false)
                    @auth
                        <x-button
                            :link="$previewEventProposeUrl"
                            class="btn-primary btn-sm sm:btn-md ui-action ui-action-propose"
                            wire:navigate
                            data-ui="listing-event-preview-propose"
                        >
                            {{ __('ui.events.propose_activity_short') }}
                        </x-button>
                    @else
                        <x-button
                            :link="$previewEventGuestProposeUrl"
                            :no-wire-navigate="true"
                            class="btn-primary btn-sm sm:btn-md ui-action ui-action-propose"
                            data-ui="listing-event-preview-propose-guest"
                        >
                            {{ __('ui.events.propose_activity_short') }}
                        </x-button>
                    @endauth
                @endif
            </div>
        </x-slot:actions>
    </x-modal>
@endif
