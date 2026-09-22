@php
    $canRequestJoin = $targetOrganization !== null
        && ! auth()->user()?->canModifyEntity($targetOrganization)
        && (int) auth()->id() !== (int) $targetOrganization->created_by
        && (int) auth()->user()?->organization_id !== (int) $targetOrganization->id;
@endphp

<div
    class="flex min-h-0 flex-1 flex-col"
    data-ui="organization-contact-popover"
    data-overlay-sticky-footer
>
    <div class="flex min-h-0 flex-1 flex-col" data-ui="organization-contact-popover-body">
        @if ($targetOrganization !== null)
            <x-ui.tabs-with-toolbar
                selected="description"
                label-bar-class="flex w-full min-w-0 items-center border-b border-base-300 gap-2"
                label-div-class="flex gap-5 overflow-x-auto px-1"
                label-class="tab tab-lifted tab-md !px-0 !py-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="relative flex min-h-0 w-full flex-1 flex-col"
                data-ui="overlay-sticky-tabs"
            >
                <x-slot:heading>
                    <div class="flex flex-col items-center gap-3 px-4 pt-4 pb-3 text-center" data-ui="organization-contact-popover-hero">
                        <div class="avatar">
                            <div class="h-28 w-28 shrink-0 overflow-hidden rounded-full border-2 border-base-300 bg-base-300 shadow-[0_0_24px_color-mix(in_oklch,var(--color-primary)_28%,transparent)]">
                                <img
                                    src="{{ $targetOrganization->logoUrl() }}"
                                    alt="{{ $targetOrganization->name }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                            </div>
                        </div>
                        <p class="max-w-full break-words px-2 text-lg font-semibold whitespace-normal text-base-content">
                            {{ $targetOrganization->name }}
                        </p>
                    </div>
                </x-slot:heading>

                <x-tab
                    name="description"
                    :label="__('ui.organizations.description_section')"
                    class="!p-0"
                    data-ui="organization-contact-popover-tab-description"
                    icon="o-document-text"
                >
                    <div class="space-y-4 p-4 text-sm" data-ui="organization-contact-popover-description">
                        @if (filled(rich_text_excerpt($targetOrganization->description)))
                            <div class="rich-text-content text-base-content/80">
                                {!! rich_text($targetOrganization->description) !!}
                            </div>
                        @else
                            <p class="text-base-content/60">{{ __('ui.organizations.no_description') }}</p>
                        @endif

                        @if ($targetOrganization->links->isNotEmpty())
                            <div class="space-y-2" data-ui="organization-contact-popover-links">
                                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.entity_links.section') }}</p>
                                <x-ui.entity-links
                                    :links="$targetOrganization->links"
                                    appearance="compact"
                                    data-ui="organization-contact-popover-entity-links"
                                />
                            </div>
                        @endif
                    </div>
                </x-tab>

                <x-tab
                    name="statistics"
                    :label="__('ui.organizations.statistics_tab')"
                    class="!p-0"
                    data-ui="organization-contact-popover-tab-statistics"
                    icon="o-chart-bar"
                >
                    <div class="space-y-5 p-4 text-sm">
                        <div class="space-y-2" data-ui="organization-contact-popover-scheduled-stats">
                            <p class="text-sm font-semibold text-base-content">{{ __('ui.organizations.scheduled_section') }}</p>
                            @forelse ($scheduledStatsByType as $stat)
                                <div class="flex items-center justify-between gap-3 text-sm text-base-content/65">
                                    <span>{{ $stat['label'] }}</span>
                                    <span class="font-semibold tabular-nums text-base-content">{{ $stat['count'] }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-base-content/50">{{ __('ui.organizations.no_scheduled_activities') }}</p>
                            @endforelse
                        </div>

                        <div class="space-y-2" data-ui="organization-contact-popover-past-stats">
                            <p class="text-sm font-semibold text-base-content">{{ __('ui.organizations.past_section') }}</p>
                            @forelse ($pastStatsByType as $stat)
                                <div class="flex items-center justify-between gap-3 text-sm text-base-content/65">
                                    <span>{{ $stat['label'] }}</span>
                                    <span class="font-semibold tabular-nums text-base-content">{{ $stat['count'] }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-base-content/50">{{ __('ui.organizations.no_past_activities') }}</p>
                            @endforelse
                        </div>
                    </div>
                </x-tab>

                <x-tab
                    name="members"
                    :label="__('ui.organizations.members_section')"
                    class="!p-0"
                    data-ui="organization-contact-popover-tab-members"
                    icon="o-users"
                >
                    <div class="space-y-2 p-4 text-sm" data-ui="organization-contact-popover-members">
                        @forelse ($members as $member)
                            <x-user-badge
                                :user="$member"
                                size="sm"
                                name-class="truncate text-sm font-medium text-base-content"
                                class="min-w-0"
                            />
                        @empty
                            <p class="text-base-content/60">{{ __('ui.organizations.no_members') }}</p>
                        @endforelse
                    </div>
                </x-tab>

                <x-tab
                    name="event_series"
                    :label="__('ui.organizations.event_series_section')"
                    class="!p-0"
                    data-ui="organization-contact-popover-tab-event-series"
                    icon="o-rectangle-stack"
                >
                    <div class="space-y-2 p-4 text-sm" data-ui="organization-contact-popover-event-series">
                        @forelse ($eventSeries as $series)
                            <a
                                href="{{ route('event-series.show', $series) }}"
                                wire:navigate
                                class="link link-primary block break-words"
                                data-ui="organization-contact-popover-series-link"
                            >{{ $series->name }}</a>
                        @empty
                            <p class="text-base-content/60">{{ __('ui.organizations.no_event_series') }}</p>
                        @endforelse
                    </div>
                </x-tab>

                <x-tab
                    name="places"
                    :label="__('ui.organizations.places_section')"
                    class="!p-0"
                    data-ui="organization-contact-popover-tab-places"
                    icon="o-map-pin"
                >
                    <div class="space-y-2 p-4 text-sm" data-ui="organization-contact-popover-places">
                        @forelse ($placeLinks as $placeLink)
                            <a
                                href="{{ $placeLink['url'] }}"
                                wire:navigate
                                class="link link-primary block break-words"
                                data-ui="organization-contact-popover-place-link"
                            >{{ $placeLink['label'] }}</a>
                        @empty
                            <p class="text-base-content/60">{{ __('ui.organizations.no_places') }}</p>
                        @endforelse
                    </div>
                </x-tab>
            </x-ui.tabs-with-toolbar>
        @endif
    </div>

    @if ($targetOrganization !== null)
        <div class="shrink-0 space-y-3 border-t border-base-300 px-4 py-3" data-ui="organization-contact-popover-footer">
            <x-button
                :link="\App\Support\Browse\BrowseSearchUrl::forOrganization($targetOrganization)"
                class="btn-primary btn-block"
                wire:navigate
                data-ui="organization-see-all-listings"
            >
                {{ __('ui.organizations.events') }}
            </x-button>

            @if ($canRequestJoin)
                <div data-ui="organization-contact-popover-requests">
                    <livewire:user-requests.send-user-request
                        type="organization_join_request"
                        subject-type="organization"
                        :subject-id="$targetOrganization->id"
                        :recipient-id="$targetOrganization->created_by"
                        :key="'organization-join-'.$targetOrganization->id"
                    />
                </div>
            @endif
        </div>
    @endif
</div>
