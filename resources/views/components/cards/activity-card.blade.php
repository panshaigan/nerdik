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

    $detailsUrl = route('activities.show', $activity);
    $currentUser = auth()->user();
    $isOwner = $currentUser !== null && (int) ($activity->created_by ?? 0) === (int) $currentUser->id;
    $isParticipating = $currentUser !== null
        && in_array((int) $activity->id, array_map('intval', $participatingActivityIds), true);
    $hostingMode = (int) ($activity->hosting_mode ?? 0);
    $proposalEvent = null;
    if ($hostingMode === Activity::HOSTING_MODE_PROPOSED_TO_EVENT) {
        $proposalEvent = $activity->relationLoaded('proposals')
            ? optional($activity->proposals->sortByDesc('id')->first())->event
            : $activity->proposals()->with('event')->latest('id')->first()?->event;
    }
    $durationLabel = format_activity_duration_compact($activity->duration_in_minutes);
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
@endphp

<article class="ui-card ui-card-activity card relative flex h-full flex-col overflow-hidden rounded-2xl bg-base-100 shadow-sm" data-ui="activity-card" id="ui-activity-card-{{ $activity->id }}">
    <div class="bg-gradient-to-br from-violet-900 via-purple-900 to-indigo-900 p-5 text-base-100" data-ui="activity-card-body">
        <div class="mb-3 flex items-start justify-between gap-3">
            <div>
                <span class="badge badge-sm border-0 bg-base-100/20 text-base-100">
                    {{ $showListingKind ? __('ui.browse.listing_kind_activity') : __('Activity') }}
                </span>
                @if ($hostingMode === Activity::HOSTING_MODE_DRAFT)
                    <span class="badge badge-sm border-0 bg-base-100/15 text-base-100/90">
                        {{ __('ui.activities.hosting_modes.draft') }}
                    </span>
                @elseif ($hostingMode === Activity::HOSTING_MODE_PROPOSED_TO_EVENT)
                    <span class="badge badge-sm border-0 bg-base-100/15 text-base-100/90">
                        {{ __('ui.activities.hosting_modes.proposed_to_event') }}
                    </span>
                    @if ($proposalEvent)
                        <a
                            href="{{ route('events.show', $proposalEvent) }}"
                            wire:navigate
                            class="ml-1 text-xs underline decoration-base-100/50 underline-offset-2 hover:decoration-base-100 relative z-20"
                        >
                            {{ $proposalEvent->name }}
                        </a>
                    @endif
                @endif
            </div>
            @auth
                <div class="relative z-20 shrink-0 flex items-center gap-1">
                    @if ($isOwner)
                        <a
                            href="{{ route('activities.edit', $activity) }}"
                            wire:navigate
                            class="btn btn-xs rounded-full border-base-100/50 bg-transparent text-base-100 hover:bg-base-100/20"
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
                            class="btn btn-xs rounded-full border-base-100/50 bg-base-100/10 text-warning hover:bg-base-100/20 ui-action ui-action-interest-remove"
                            :title="__('ui.interests.remove_from_interests')"
                            data-ui="activity-card-interest-remove"
                        >★</x-button>
                    @else
                        <x-button
                            type="button"
                            wire:click.stop="toggleActivityInterest({{ (int) $activity->id }})"
                            class="btn btn-xs rounded-full border-base-100/50 bg-transparent text-base-100 hover:bg-base-100/20 ui-action ui-action-interest-add"
                            :title="__('ui.interests.add_to_interests')"
                            data-ui="activity-card-interest-add"
                        >☆</x-button>
                    @endif
                </div>
            @endauth
        </div>

        <h3 class="mb-1 text-3xl font-bold leading-tight">
            <span class="ui-link ui-link-title" data-ui="activity-card-title-link">{{ $activity->name }}</span>
        </h3>

        @if ($activity->creator)
            <x-user-badge
                :user="$activity->creator"
                size="sm"
                name-class="truncate text-sm font-semibold text-base-100/90"
                class="text-base-100/90 [&_.avatar>div]:border-base-100/30 [&_.avatar>div]:bg-base-100/20 [&_.avatar>div]:text-base-100"
            />
        @endif

        @if ($isParticipating)
            <p class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-success-content">
                <x-icon name="o-check-circle" class="h-4 w-4" />
                <span>{{ __('ui.activities.show_participation') }}</span>
            </p>
        @endif

        @if ($activity->slot && $activity->slot->event)
            <p class="text-sm text-base-100/75">
                {{ __('ui.browse.attached_event') }}:
                <a href="{{ route('events.show', $activity->slot->event) }}" wire:navigate class="underline decoration-base-100/50 underline-offset-2 hover:decoration-base-100">
                    {{ $activity->slot->event->name }}
                </a>
            </p>
        @endif
    </div>

    <div class="flex grow flex-col space-y-4 p-5">
        <div class="grid grid-cols-2 gap-4 text-sm text-base-content/80">
            @if ($timeSummary !== '')
                <div class="space-y-0.5">
                    <p class="font-semibold text-base-content/75">{{ __('Date') }}:</p>
                    <p class="flex items-center gap-1.5 text-base-content">
                        <x-icon name="o-clock" class="h-4 w-4 text-base-content/60" />
                        <span>{{ $timeSummary }}</span>
                    </p>
                </div>
            @endif

            @if (filled($venueName))
                <div class="space-y-0.5">
                    <p class="font-semibold text-base-content/75">{{ __('Location') }}:</p>
                    <p class="flex items-center gap-1.5 text-base-content">
                        <x-icon name="o-home" class="h-4 w-4 text-base-content/60" />
                        <span>{{ $venueName }}</span>
                    </p>
                </div>
            @endif

            <div class="space-y-0.5">
                <p class="font-semibold text-base-content/75">{{ __('ui.browse.participants_count') }}:</p>
                <p class="flex items-center gap-1.5 tabular-nums text-base-content">
                    <x-icon name="o-users" class="h-4 w-4 text-base-content/60" />
                    @if ($max !== null)
                        <span>{{ __('ui.browse.participants_filled_max', ['filled' => $filled, 'max' => $max]) }}</span>
                    @else
                        <span>{{ __('ui.browse.participants_filled_no_cap', ['filled' => $filled]) }}</span>
                    @endif
                </p>
            </div>
        </div>

        @if ($durationLabel)
            <p class="text-sm text-base-content/80">{{ __('ui.browse.duration_label') }}: {{ $durationLabel }}</p>
        @endif

        <div class="mt-auto">
            <x-ui.activity-badge-group
                :items="$activityBadgeItems"
                class="!my-0 gap-2"
                data-ui="activity-card-badge-group"
            />
        </div>
    </div>
    <a
        href="{{ $detailsUrl }}"
        wire:navigate
        class="absolute inset-0 z-10"
        aria-label="{{ __('Open activity') }}: {{ $activity->name }}"
        data-ui="activity-card-link"
    ></a>
</article>
