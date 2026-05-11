@props([
    'event',
    'interestedEventIds' => [],
    'participatingEventIds' => [],
    'showListingKind' => false,
])

@php
    use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
    use App\Enums\BadgeSemantic;
    use Illuminate\Support\Facades\Storage;

    $detailsUrl = route('events.show', $event);
    $currentUser = auth()->user();
    $isOwner = $currentUser !== null && (int) ($event->created_by ?? 0) === (int) $currentUser->id;
    $locale = app()->getLocale();
    $locationLabels = [];
    foreach ($event->places as $place) {
        $city = $place->city?->name($locale);
        $country = $place->country?->name($locale);
        $c = $city ? trim($city) : null;
        $co = $country ? trim($country) : null;
        if ($c !== null && $co !== null && mb_strtolower($c) === mb_strtolower($co)) {
            $label = $c;
        } else {
            $label = implode(', ', array_filter([$c, $co]));
        }
        if ($label !== '') {
            $locationLabels[mb_strtolower($label)] = $label;
        }
    }
    $locationFallback = implode(' · ', array_values($locationLabels));
    $venueSummary = $event->compactPlaceSummary();
    $locationSummary = $venueSummary !== '' ? $venueSummary : $locationFallback;
    $dateSummary = format_date_range_compact($event->starts_at, $event->ends_at);
    $slotTypeLabels = collect($event->slots ?? [])
        ->flatMap(function ($slot) {
            $activityTypeFromActivity = $slot->activity?->activityType?->slug
                ? [__('ui.activities.types.'.$slot->activity->activityType->slug)]
                : [];

            $allowedTypesFromSlot = collect($slot->activityTypes ?? [])
                ->map(fn ($row) => $row->slug ? __('ui.activities.types.'.$row->slug) : null)
                ->filter()
                ->values()
                ->all();

            return array_merge($activityTypeFromActivity, $allowedTypesFromSlot);
        })
        ->filter()
        ->unique()
        ->values();
    $slotTypeBadgeItems = $slotTypeLabels->isNotEmpty()
        ? app(ActivityBadgeGroupBuilder::class)->buildActivityTypeChips($slotTypeLabels, BadgeSemantic::Info)
        : [];
    $logoUrl = filled($event->logo_path ?? null)
        ? Storage::disk('public')->url($event->logo_path)
        : null;
@endphp

<article class="ui-card ui-card-event card group relative flex h-full flex-col" data-ui="event-card" id="ui-event-card-{{ $event->id }}">
    <div class="ui-browse-listing-card-frame flex min-h-0 flex-1 flex-col p-px">
        <div class="ui-browse-listing-card-inner flex min-h-0 flex-1 flex-col overflow-hidden shadow-lg">
            <div class="relative aspect-video w-full shrink-0 bg-linear-to-br from-slate-900 via-slate-950 to-slate-900">
                @if ($logoUrl)
                    <img
                        src="{{ $logoUrl }}"
                        alt=""
                        class="h-full w-full object-cover"
                        loading="lazy"
                    />
                @else
                    <div class="flex h-full w-full items-center justify-center bg-linear-to-tl from-slate-800/80 via-slate-900 to-indigo-950/90" aria-hidden="true">
                        <x-icon name="o-square-2-stack" class="h-14 w-14 text-slate-500/80" />
                    </div>
                @endif
                <div class="pointer-events-none absolute inset-0 bg-linear-to-t from-black/55 to-transparent opacity-90"></div>
                <div class="absolute right-2 top-2 z-20 flex max-w-[min(100%,12rem)] flex-col items-end gap-1.5">
                    @auth
                        <div class="pointer-events-auto flex shrink-0 items-center gap-1">
                            @if ($isOwner)
                                <a
                                    href="{{ route('events.edit', $event) }}"
                                    wire:navigate
                                    class="btn btn-xs rounded-lg border border-cyan-400/35 bg-black/55 text-cyan-100/95 backdrop-blur-sm hover:bg-black/70"
                                    title="{{ __('ui.events.edit_event') }}"
                                    data-ui="event-card-edit"
                                >
                                    <x-icon name="o-pencil" class="h-3.5 w-3.5" />
                                </a>
                            @endif
                            @if (in_array($event->id, $interestedEventIds))
                                <x-button
                                    type="button"
                                    wire:click.stop="toggleEventInterest({{ (int) $event->id }})"
                                    class="btn btn-xs rounded-lg border border-amber-400/40 bg-black/55 text-amber-200 backdrop-blur-sm hover:bg-black/70 ui-action ui-action-interest-remove"
                                    :title="__('ui.interests.remove_from_interests')"
                                    data-ui="event-card-interest-remove"
                                >★</x-button>
                            @else
                                <x-button
                                    type="button"
                                    wire:click.stop="toggleEventInterest({{ (int) $event->id }})"
                                    class="btn btn-xs rounded-lg border border-cyan-400/35 bg-black/55 text-cyan-100/90 backdrop-blur-sm hover:bg-black/70 ui-action ui-action-interest-add"
                                    :title="__('ui.interests.add_to_interests')"
                                    data-ui="event-card-interest-add"
                                >☆</x-button>
                            @endif
                        </div>
                    @endauth
                    <div class="flex flex-wrap justify-end gap-1">
                        @if ($showListingKind)
                            <span class="inline-flex items-center justify-center rounded-md border border-fuchsia-400/30 bg-black/55 p-1.5 text-fuchsia-200/95 backdrop-blur-sm" title="{{ __('ui.browse.listing_kind_event') }}">
                                <x-icon name="o-calendar-days" class="h-4 w-4" />
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="relative flex min-h-0 flex-1 flex-col p-4 pt-3">
                <h3 class="text-lg font-bold leading-snug text-white sm:text-xl">
                    <span class="ui-link ui-link-title" data-ui="event-card-title-link">{{ $event->name }}</span>
                </h3>

                <dl class="mt-3 min-h-0 flex-1 space-y-2.5 text-sm">
                    @if ($dateSummary !== '')
                        <div class="flex gap-2">
                            <dt class="sr-only">{{ __('Date') }}</dt>
                            <dd class="flex min-w-0 flex-1 gap-2 text-slate-400">
                                <x-icon name="o-calendar" class="mt-0.5 h-4 w-4 shrink-0 text-cyan-400/70" />
                                <span class="min-w-0 leading-snug">
                                    <span class="font-medium text-slate-500">{{ __('Date') }}:</span>
                                    {{ $dateSummary }}
                                </span>
                            </dd>
                        </div>
                    @endif
                    @if ($locationSummary !== '')
                        <div class="flex gap-2">
                            <dt class="sr-only">{{ __('Location') }}</dt>
                            <dd class="flex min-w-0 flex-1 gap-2 text-slate-400">
                                <x-icon name="o-map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-cyan-400/70" />
                                <span class="min-w-0 leading-snug">
                                    <span class="font-medium text-slate-500">{{ __('Location') }}:</span>
                                    {{ $locationSummary }}
                                </span>
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($slotTypeBadgeItems !== [])
                    <div class="mt-auto border-t border-white/5 pt-3">
                        <x-ui.activity-badge-group
                            :items="$slotTypeBadgeItems"
                            class="ui-browse-listing-card-tags !my-0 gap-2"
                            data-ui="event-card-slot-type-badges"
                        />
                    </div>
                @endif
            </div>
        </div>
    </div>

    <a
        href="{{ $detailsUrl }}"
        wire:navigate
        class="absolute inset-0 z-10 rounded-2xl"
        aria-label="{{ __('Open event') }}: {{ $event->name }}"
        data-ui="event-card-link"
    ></a>
</article>
