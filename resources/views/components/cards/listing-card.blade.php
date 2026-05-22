@php
    $d = $viewData;
@endphp

<article
    class="ui-card ui-listing-card {{ $d->cardModifierClass }} card group relative flex h-full flex-col"
    data-ui="{{ $d->dataUiPrefix }}"
    id="ui-{{ $d->dataUiPrefix }}-{{ $d->id }}"
>
    @auth
        <div class="ui-listing-card__toolbar pointer-events-auto absolute right-2 top-2 z-30 flex shrink-0 items-center gap-1">
            @if ($d->isOwner)
                <x-button
                    :link="$d->editUrl"
                    class="btn btn-xs rounded-lg border border-cyan-400/35 bg-black/70 text-cyan-100/95 hover:bg-black/85"
                    :aria-label="$d->editTitle"
                    icon="o-pencil"
                    data-ui="{{ $d->dataUiPrefix }}-edit"
                />
            @endif
            @if ($d->isInterested)
                <x-button
                    type="button"
                    wire:click.stop="{{ $d->interestWireMethod }}({{ $d->id }})"
                    class="btn btn-xs rounded-lg border border-amber-400/40 bg-black/70 text-amber-200 hover:bg-black/85 ui-action ui-action-interest-remove"
                    :aria-label="__('ui.interests.remove_from_interests')"
                    data-ui="{{ $d->dataUiPrefix }}-interest-remove"
                >★</x-button>
            @else
                <x-button
                    type="button"
                    wire:click.stop="{{ $d->interestWireMethod }}({{ $d->id }})"
                    class="btn btn-xs rounded-lg border border-cyan-400/35 bg-black/70 text-cyan-100/90 hover:bg-black/85 ui-action ui-action-interest-add"
                    :aria-label="__('ui.interests.add_to_interests')"
                    data-ui="{{ $d->dataUiPrefix }}-interest-add"
                >☆</x-button>
            @endif
        </div>
    @endauth
    <div class="ui-listing-card__surface ui-content-card flex min-h-0 flex-1 flex-col overflow-visible">
        <div class="ui-listing-card__media relative aspect-video w-full shrink-0 overflow-visible bg-transparent">
            @if ($d->logoUrl)
                <img
                    src="{{ $d->logoUrl }}"
                    alt=""
                    class="ui-card-media-fade h-full w-full object-cover"
                    loading="lazy"
                />
            @else
                <img
                    src="{{ asset('images/tag-game/warhammer.jpg') }}"
                    alt=""
                    class="ui-card-media-fade h-full w-full object-cover rounded-2xl"
                    loading="lazy"
                />
            @endif
            <span
                class="absolute left-2 top-2 z-20 max-w-[min(100%,12rem)] truncate rounded-md border border-amber-400/35 bg-black/70 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-amber-100/95"
                data-ui="{{ $d->dataUiPrefix }}-kind-label"
            >{{ $d->kindCornerLabel }}</span>
        </div>
        <div class="relative flex min-h-0 flex-1 flex-col px-3 pb-2">
            <h3 class="text-lg font-bold leading-snug text-neutral sm:text-xl">
                <span class="ui-link ui-link-title" data-ui="{{ $d->dataUiPrefix }}-title-link">{{ $d->name }}</span>
            </h3>
            @if ($d->hostUser)
                <div class="mt-1 pb-2 mb-3">
                    <x-user-badge
                        :user="$d->hostUser"
                        :organization="$d->hostOrganization"
                        size="sm"
                        nameClass="truncate text-xs font-medium text-slate-400"
                        data-ui="{{ $d->dataUiPrefix }}-host"
                    />
                </div>
            @endif
            <dl class="mt-3 mb-3 min-h-0 flex-1 space-y-2.5 text-sm">
                @if ($d->timeSummary !== '')
                    <div class="flex gap-2">
                        <dt class="sr-only">{{ __('Date') }}</dt>
                        <dd class="flex min-w-0 flex-1 gap-2 text-slate-400">
                            <x-icon name="o-calendar" class="mt-0.5 h-4 w-4 shrink-0 text-cyan-400/70" />
                            <span class="min-w-0 leading-snug">
                                <span class="font-medium text-slate-500">{{ __('Date') }}:</span>
                                {{ $d->timeSummary }}
                            </span>
                        </dd>
                    </div>
                @endif
                @if ($d->locationSummary !== '')
                    <div class="flex gap-2">
                        <dt class="sr-only">{{ __('Location') }}</dt>
                        <dd class="flex min-w-0 flex-1 gap-2 text-slate-400">
                            <x-icon name="o-map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-cyan-400/70" />
                            <span class="min-w-0 leading-snug">
                                <span class="font-medium text-slate-500">{{ __('Location') }}:</span>
                                {{ $d->locationSummary }}
                            </span>
                        </dd>
                    </div>
                @endif
                @if ($d->showParticipants)
                    <div class="flex gap-2" data-ui="browse-card-participants">
                        <dt class="sr-only">{{ __('ui.browse.participants_count') }}</dt>
                        <dd class="flex min-w-0 flex-1 gap-2 text-slate-400">
                            <x-icon name="o-users" class="mt-0.5 h-4 w-4 shrink-0 text-cyan-400/70" />
                            <span class="min-w-0 leading-snug tabular-nums">
                                <span class="font-medium text-slate-500">{{ __('ui.browse.participants_count') }}:</span>
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
                        <dd class="flex min-w-0 flex-1 gap-2 text-slate-400">
                            <x-icon name="o-calendar-days" class="mt-0.5 h-4 w-4 shrink-0 text-cyan-400/70" />
                            <span class="min-w-0 leading-snug">
                                <span class="font-medium text-slate-500">{{ __('ui.browse.parent_event') }}:</span>
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
            </dl>
        </div>
        @if ($d->badgeItems !== [])
            <div class="relative z-20 p-4 pointer-events-auto">
                <x-ui.activity-badge-group
                    :items="$d->badgeItems"
                    class="ui-browse-listing-card-tags !my-0 gap-2"
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
