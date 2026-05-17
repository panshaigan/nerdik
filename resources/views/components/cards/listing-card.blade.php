@php
    $d = $viewData;
@endphp

<article
    class="ui-card ui-listing-card {{ $d->cardModifierClass }} card group relative flex h-full flex-col"
    data-ui="{{ $d->dataUiPrefix }}"
    id="ui-{{ $d->dataUiPrefix }}-{{ $d->id }}"
>
    <div class="ui-listing-card__surface ui-content-card flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="relative aspect-video w-full shrink-0 bg-transparent">
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
                    class="ui-card-media-fade h-full w-full object-cover"
                    loading="lazy"
                />
            @endif
            <div class="absolute right-2 top-2 z-20 flex max-w-[min(100%,12rem)] flex-col items-end gap-1.5">
                @auth
                    <div class="pointer-events-auto flex shrink-0 items-center gap-1">
                        @if ($d->isOwner)
                            <a
                                href="{{ $d->editUrl }}"
                                wire:navigate
                                class="btn btn-xs rounded-lg border border-cyan-400/35 bg-black/70 text-cyan-100/95 hover:bg-black/85"
                                title="{{ $d->editTitle }}"
                                data-ui="{{ $d->dataUiPrefix }}-edit"
                            >
                                <x-icon name="o-pencil" class="h-3.5 w-3.5" />
                            </a>
                        @endif
                        @if ($d->isInterested)
                            <x-button
                                type="button"
                                wire:click.stop="{{ $d->interestWireMethod }}({{ $d->id }})"
                                class="btn btn-xs rounded-lg border border-amber-400/40 bg-black/70 text-amber-200 hover:bg-black/85 ui-action ui-action-interest-remove"
                                :title="__('ui.interests.remove_from_interests')"
                                data-ui="{{ $d->dataUiPrefix }}-interest-remove"
                            >★</x-button>
                        @else
                            <x-button
                                type="button"
                                wire:click.stop="{{ $d->interestWireMethod }}({{ $d->id }})"
                                class="btn btn-xs rounded-lg border border-cyan-400/35 bg-black/70 text-cyan-100/90 hover:bg-black/85 ui-action ui-action-interest-add"
                                :title="__('ui.interests.add_to_interests')"
                                data-ui="{{ $d->dataUiPrefix }}-interest-add"
                            >☆</x-button>
                        @endif
                    </div>
                @endauth
                <div class="flex flex-wrap justify-end gap-1">
                    @if ($d->hostingCornerLabel !== null)
                        <span class="max-w-full truncate rounded-md border border-amber-400/35 bg-black/70 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-amber-100/95">
                            {{ $d->hostingCornerLabel }}
                        </span>
                    @endif
                    @if ($showListingKind)
                        <span
                            class="inline-flex items-center justify-center rounded-md border border-fuchsia-400/30 bg-black/70 p-1.5 text-fuchsia-200/95"
                            title="{{ $d->listingKindTitle }}"
                        >
                            <x-icon :name="$d->listingKindIcon" class="h-4 w-4" />
                        </span>
                    @endif
                </div>
            </div>
        </div>
        <div class="relative flex min-h-0 flex-1 flex-col px-3 pb-2">
            <h3 class="text-lg font-bold leading-snug text-white sm:text-xl">
                <span class="ui-link ui-link-title" data-ui="{{ $d->dataUiPrefix }}-title-link">{{ $d->name }}</span>
            </h3>
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
            </dl>
        </div>
        @if ($d->badgeItems !== [])
            <div class="p-4">
                <x-ui.activity-badge-group
                    :items="$d->badgeItems"
                    class="ui-browse-listing-card-tags !my-0 gap-2"
                    :data-ui="$d->badgeGroupDataUi"
                />
            </div>
        @endif
    </div>

    <a
        href="{{ $d->detailsUrl }}"
        wire:navigate
        class="absolute inset-0 z-10 rounded-2xl"
        aria-label="{{ $d->openAriaLabel }}"
        data-ui="{{ $d->dataUiPrefix }}-link"
    ></a>
</article>
