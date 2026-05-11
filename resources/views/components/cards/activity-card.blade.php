@props([
    'activity',
    'interestedActivityIds' => [],
    'participatingActivityIds' => [],
    'showListingKind' => false,
])

@php
    use App\Domain\ActivityBadges\ActivityBadgeGroupConfig;
    use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
    use App\Models\Activity;
    use Illuminate\Support\Facades\Storage;

    $detailsUrl = route('activities.show', $activity);
    $currentUser = auth()->user();
    $isOwner = $currentUser !== null && (int) ($activity->created_by ?? 0) === (int) $currentUser->id;
    $hostingMode = (int) ($activity->hosting_mode ?? 0);
    $filled = isset($activity->participants_count)
        ? (int) $activity->participants_count
        : (int) $activity->participants()->where('is_absent', false)->count();
    $max = $activity->max_participants;
    $timeSourceStartsAt = $activity->slot?->starts_at ?? $activity->starts_at;
    $timeSourceEndsAt = $activity->slot?->ends_at ?? $activity->ends_at;
    $timeSummary = format_date_range_compact($timeSourceStartsAt, $timeSourceEndsAt);
    $venueName = $activity->slot?->place?->name ?? $activity->place?->name;

    $activityBadgeItems = app(ActivityBadgeGroupBuilder::class)->build(
        $activity,
        ActivityBadgeGroupConfig::browseCard(),
    );

    $hostingCornerLabel = match ($hostingMode) {
        Activity::HOSTING_MODE_DRAFT => __('ui.activities.hosting_modes.draft'),
        Activity::HOSTING_MODE_PROPOSED_TO_EVENT => __('ui.activities.hosting_modes.proposed_to_event'),
        default => null,
    };

    $logoUrl = filled($activity->logo_path ?? null)
        ? Storage::disk('public')->url($activity->logo_path)
        : null;
@endphp

<article class="ui-card ui-card-activity card group relative flex h-full flex-col" data-ui="activity-card" id="ui-activity-card-{{ $activity->id }}">
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
                    <div class="flex h-full w-full items-center justify-center bg-linear-to-tl from-indigo-950/90 via-slate-900 to-violet-950/80" aria-hidden="true">
                        <x-icon name="o-square-2-stack" class="h-14 w-14 text-slate-500/80" />
                    </div>
                @endif
                <div class="pointer-events-none absolute inset-0 bg-linear-to-t from-black/55 to-transparent opacity-90"></div>
                <div class="absolute right-2 top-2 z-20 flex max-w-[min(100%,12rem)] flex-col items-end gap-1.5">
                    @auth
                        <div class="pointer-events-auto flex shrink-0 items-center gap-1">
                            @if ($isOwner)
                                <a
                                    href="{{ route('activities.edit', $activity) }}"
                                    wire:navigate
                                    class="btn btn-xs rounded-lg border border-cyan-400/35 bg-black/55 text-cyan-100/95 backdrop-blur-sm hover:bg-black/70"
                                    title="{{ __('ui.activities.edit_activity') }}"
                                    data-ui="activity-card-edit"
                                >
                                    <x-icon name="o-pencil" class="h-3.5 w-3.5" />
                                </a>
                            @endif
                            @if (in_array($activity->id, $interestedActivityIds))
                                <x-button
                                    type="button"
                                    wire:click.stop="toggleActivityInterest({{ (int) $activity->id }})"
                                    class="btn btn-xs rounded-lg border border-amber-400/40 bg-black/55 text-amber-200 backdrop-blur-sm hover:bg-black/70 ui-action ui-action-interest-remove"
                                    :title="__('ui.interests.remove_from_interests')"
                                    data-ui="activity-card-interest-remove"
                                >★</x-button>
                            @else
                                <x-button
                                    type="button"
                                    wire:click.stop="toggleActivityInterest({{ (int) $activity->id }})"
                                    class="btn btn-xs rounded-lg border border-cyan-400/35 bg-black/55 text-cyan-100/90 backdrop-blur-sm hover:bg-black/70 ui-action ui-action-interest-add"
                                    :title="__('ui.interests.add_to_interests')"
                                    data-ui="activity-card-interest-add"
                                >☆</x-button>
                            @endif
                        </div>
                    @endauth
                    <div class="flex flex-wrap justify-end gap-1">
                        @if ($hostingCornerLabel !== null)
                            <span class="max-w-full truncate rounded-md border border-amber-400/35 bg-black/55 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-amber-100/95 backdrop-blur-sm">
                                {{ $hostingCornerLabel }}
                            </span>
                        @endif
                        @if ($showListingKind)
                            <span class="inline-flex items-center justify-center rounded-md border border-fuchsia-400/30 bg-black/55 p-1.5 text-fuchsia-200/95 backdrop-blur-sm" title="{{ __('ui.browse.listing_kind_activity') }}">
                                <x-icon name="o-squares-2x2" class="h-4 w-4" />
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="relative flex min-h-0 flex-1 flex-col p-4 pt-3">
                <h3 class="text-lg font-bold leading-snug text-white sm:text-xl">
                    <span class="ui-link ui-link-title" data-ui="activity-card-title-link">{{ $activity->name }}</span>
                </h3>

                <dl class="mt-3 min-h-0 flex-1 space-y-2.5 text-sm">
                    @if ($timeSummary !== '')
                        <div class="flex gap-2">
                            <dt class="sr-only">{{ __('Date') }}</dt>
                            <dd class="flex min-w-0 flex-1 gap-2 text-slate-400">
                                <x-icon name="o-calendar" class="mt-0.5 h-4 w-4 shrink-0 text-cyan-400/70" />
                                <span class="min-w-0 leading-snug">
                                    <span class="font-medium text-slate-500">{{ __('Date') }}:</span>
                                    {{ $timeSummary }}
                                </span>
                            </dd>
                        </div>
                    @endif
                    @if (filled($venueName))
                        <div class="flex gap-2">
                            <dt class="sr-only">{{ __('Location') }}</dt>
                            <dd class="flex min-w-0 flex-1 gap-2 text-slate-400">
                                <x-icon name="o-map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-cyan-400/70" />
                                <span class="min-w-0 leading-snug">
                                    <span class="font-medium text-slate-500">{{ __('Location') }}:</span>
                                    {{ $venueName }}
                                </span>
                            </dd>
                        </div>
                    @endif
                    <div class="flex gap-2" data-ui="browse-card-participants">
                        <dt class="sr-only">{{ __('ui.browse.participants_count') }}</dt>
                        <dd class="flex min-w-0 flex-1 gap-2 text-slate-400">
                            <x-icon name="o-users" class="mt-0.5 h-4 w-4 shrink-0 text-cyan-400/70" />
                            <span class="min-w-0 leading-snug tabular-nums">
                                <span class="font-medium text-slate-500">{{ __('ui.browse.participants_count') }}:</span>
                                @if ($max !== null)
                                    {{ __('ui.browse.participants_filled_max', ['filled' => $filled, 'max' => $max]) }}
                                @else
                                    {{ __('ui.browse.participants_filled_no_cap', ['filled' => $filled]) }}
                                @endif
                            </span>
                        </dd>
                    </div>
                </dl>

                @if ($activityBadgeItems !== [])
                    <div class="mt-auto border-t border-white/5 pt-3">
                        <x-ui.activity-badge-group
                            :items="$activityBadgeItems"
                            class="ui-browse-listing-card-tags !my-0 gap-2"
                            data-ui="activity-card-badge-group"
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
        aria-label="{{ __('Open activity') }}: {{ $activity->name }}"
        data-ui="activity-card-link"
    ></a>
</article>
