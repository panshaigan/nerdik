@php
    $d = $viewData;
@endphp

<article
    class="ui-card ui-listing-card {{ $d->cardModifierClass }} card group relative flex h-full flex-col"
    data-ui="{{ $d->dataUiPrefix }}"
    id="ui-{{ $d->dataUiPrefix }}-{{ $d->id }}"
>
    <div class="ui-listing-card__toolbar pointer-events-auto absolute right-2 top-2 z-30 flex shrink-0 items-start gap-1">
        @auth
            @if ($d->showDetailsLink)
                <x-button
                    :link="$d->detailsUrl"
                    wire:navigate
                    class="ui-listing-card__tool ui-listing-card__tool--details btn btn-xs btn-square rounded-lg"
                    :aria-label="$d->openDetailsAriaLabel"
                    icon="o-arrow-top-right-on-square"
                    data-ui="{{ $d->dataUiPrefix }}-open-details"
                />
            @endif
            <div class="flex flex-col items-center gap-1">
                @if ($d->isInterested)
                    <x-button
                        type="button"
                        wire:click.stop="{{ $d->interestWireMethod }}({{ $d->id }})"
                        class="ui-listing-card__tool ui-listing-card__tool--interest ui-listing-card__tool--interested btn btn-xs btn-square rounded-lg ui-action ui-action-interest-remove"
                        :aria-label="__('ui.interests.remove_from_interests')"
                        icon="s-star"
                        data-ui="{{ $d->dataUiPrefix }}-interest-remove"
                    />
                @else
                    <x-button
                        type="button"
                        wire:click.stop="{{ $d->interestWireMethod }}({{ $d->id }})"
                        class="ui-listing-card__tool ui-listing-card__tool--interest btn btn-xs btn-square rounded-lg ui-action ui-action-interest-add"
                        :aria-label="__('ui.interests.add_to_interests')"
                        icon="o-star"
                        data-ui="{{ $d->dataUiPrefix }}-interest-add"
                    />
                @endif
                @if ($d->canEdit)
                    <x-button
                        :link="$d->editUrl"
                        class="ui-listing-card__tool ui-listing-card__tool--accent btn btn-xs btn-square rounded-lg"
                        :aria-label="$d->editTitle"
                        icon="o-pencil"
                        data-ui="{{ $d->dataUiPrefix }}-edit"
                    />
                @endif
            </div>
        @else
            @if ($d->showDetailsLink)
                <x-button
                    :link="$d->detailsUrl"
                    wire:navigate
                    class="ui-listing-card__tool ui-listing-card__tool--details btn btn-xs btn-square rounded-lg"
                    :aria-label="$d->openDetailsAriaLabel"
                    icon="o-arrow-top-right-on-square"
                    data-ui="{{ $d->dataUiPrefix }}-open-details"
                />
            @endif
        @endauth
    </div>
    <div class="ui-listing-card__surface ui-content-card flex min-h-0 flex-1 flex-col overflow-visible">
        <div class="ui-listing-card__media relative aspect-video w-full shrink-0 overflow-hidden rounded-2xl bg-transparent">
            <x-listing-card-picture
                :picture="$d->coverPicture"
                class="ui-card-media-fade absolute inset-0 block size-full object-cover"
            />
            <div class="pointer-events-auto absolute left-2 top-2 z-20 flex max-w-[calc(100%-3.5rem)] flex-row flex-wrap items-center gap-1">
                @if ($d->kindCornerLabel)
                    <span
                        class="shrink-0 rounded-md border border-amber-400/35 bg-black/70 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-amber-100/95"
                        data-ui="{{ $d->dataUiPrefix }}-kind-label"
                    >{{ $d->kindCornerLabel }}</span>
                @endif
                @if ($d->hasActiveEnrollmentWindow)
                    <span
                        class="shrink-0 rounded-md border border-accent/35 bg-black/70 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-accent/95"
                        data-ui="event-card-enrollment-open"
                    >{{ __('ui.events.enrollment_window_active_badge') }}</span>
                @endif
                @if ($d->kind === 'activity' && $d->hostUser)
                    <x-user-badge
                        :user="$d->hostUser"
                        size="sm"
                        nameClass="truncate text-xs font-medium text-amber-50"
                        class="max-w-full rounded-full bg-black/70 py-0.5 pl-0.5 pr-2"
                        :contact-wire-key="'listing-'.$d->kind.'-'.$d->id"
                    />
                @endif
            </div>
        </div>
        <div class="relative flex min-h-0 flex-1 flex-col px-3 pb-2">
            <h3 class="text-lg font-bold leading-snug text-neutral sm:text-xl">
                <span class="ui-link ui-link-title" data-ui="{{ $d->dataUiPrefix }}-title-link">{{ $d->name }}</span>
            </h3>
            <dl class="mt-3 mb-3 min-h-0 flex-1 space-y-2.5 text-sm">
                @if ($d->timeSummary !== '')
                    <div class="flex gap-2">
                        <dt class="sr-only">{{ __('ui.browse.date_label') }}</dt>
                        <dd class="flex min-w-0 flex-1 gap-2 text-base-content">
                            <x-icon name="o-calendar" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            <span class="min-w-0 leading-snug">
                                {{ $d->timeSummary }}
                            </span>
                        </dd>
                    </div>
                @endif
                @if ($d->locationSummary !== '')
                    <div class="flex gap-2">
                        <dt class="sr-only">{{ __('ui.browse.location_label') }}</dt>
                        <dd class="flex min-w-0 flex-1 gap-2 text-base-content">
                            <x-icon name="o-map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            <span class="min-w-0 leading-snug">
                                {{ $d->locationSummary }}
                            </span>
                        </dd>
                    </div>
                @endif
                @if ($d->showParticipants)
                    <div class="flex gap-2" data-ui="browse-card-participants">
                        <dt class="sr-only">{{ __('ui.browse.participants_count') }}</dt>
                        <dd class="flex min-w-0 flex-1 gap-2 text-base-content">
                            <x-icon name="o-users" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            <span class="min-w-0 leading-snug tabular-nums">
                                @if ($d->participantsMax !== null)
                                    {{ __('ui.browse.participants_filled_max', ['filled' => $d->participantsFilled, 'max' => $d->participantsMax]) }}
                                @else
                                    {{ __('ui.browse.participants_filled_no_cap', ['filled' => $d->participantsFilled]) }}
                                @endif
                            </span>
                        </dd>
                    </div>
                @endif
                @if ($d->parentEventName !== null && $d->parentEventUrl !== null)
                    <div class="relative z-20 flex gap-2 pointer-events-auto" data-ui="activity-card-parent-event">
                        <dt class="sr-only">{{ __('ui.browse.parent_event') }}</dt>
                        <dd class="flex min-w-0 flex-1 gap-2 text-base-content">
                            <x-icon name="o-calendar-days" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            <span class="min-w-0 leading-snug">
                                <a
                                    href="{{ $d->parentEventUrl }}"
                                    wire:navigate
                                    class="link link-primary break-words"
                                    data-ui="activity-card-parent-event-link"
                                >{{ $d->parentEventName }}</a>
                            </span>
                        </dd>
                    </div>
                @endif
                @if ($d->seriesName !== null && $d->seriesUrl !== null)
                    <div class="relative z-20 flex gap-2 pointer-events-auto" data-ui="event-card-series">
                        <dt class="sr-only">{{ __('ui.browse.event_series') }}</dt>
                        <dd class="flex min-w-0 flex-1 gap-2 text-base-content">
                            <x-icon name="o-rectangle-stack" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            <span class="min-w-0 leading-snug">
                                <a
                                    href="{{ $d->seriesUrl }}"
                                    wire:navigate
                                    class="link link-primary break-words"
                                    data-ui="event-card-series-link"
                                >{{ __('ui.browse.event_series_label', ['name' => $d->seriesName]) }}</a>
                            </span>
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
        @if ($d->badgeItems !== [])
            <div class="relative z-20 p-4 pointer-events-auto">
                <x-ui.activity-badge-group
                    :items="$d->badgeItems"
                    class="!my-0 gap-2"
                    :data-ui="$d->badgeGroupDataUi"
                />
            </div>
        @endif
        <button
            type="button"
            wire:click="{{ $d->previewWireMethod }}({{ $d->id }})"
            wire:loading.attr="disabled"
            wire:target="{{ $d->previewWireMethod }}({{ $d->id }})"
            wire:loading.class.delay="cursor-wait"
            class="absolute inset-0 z-10 block cursor-pointer rounded-2xl bg-transparent"
            aria-label="{{ $d->openAriaLabel }}"
            data-ui="{{ $d->dataUiPrefix }}-open-preview"
        ></button>
        <div
            wire:loading.delay
            wire:target="{{ $d->previewWireMethod }}({{ $d->id }})"
            class="pointer-events-none absolute inset-0 z-[15] flex items-center justify-center rounded-2xl bg-base-100/50 backdrop-blur-[1px]"
            aria-live="polite"
            role="status"
            data-ui="{{ $d->dataUiPrefix }}-preview-loading"
        >
            <span class="sr-only">{{ __('ui.common.loading') }}</span>
            <span class="loading loading-spinner loading-lg text-primary" aria-hidden="true"></span>
        </div>
    </div>
</article>
