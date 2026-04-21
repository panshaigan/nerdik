@props([
    'activity',
    'interestedActivityIds' => [],
    'showListingKind' => false,
])

@php
    use App\Domain\ActivityBadges\ActivityBadgeGroupConfig;
    use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;

    $durationLabel = format_activity_duration_compact($activity->duration_in_minutes);
    $filled = isset($activity->participants_count)
        ? (int) $activity->participants_count
        : (int) $activity->participants()->where('is_absent', false)->count();
    $max = $activity->max_participants;

    $activityBadgeItems = app(ActivityBadgeGroupBuilder::class)->build(
        $activity,
        ActivityBadgeGroupConfig::browseCard(),
    );
@endphp

<article class="ui-card ui-card-activity card border border-base-300 bg-base-100 shadow-sm" data-ui="activity-card" id="ui-activity-card-{{ $activity->id }}">
    <div class="card-body p-5" data-ui="activity-card-body">
        @if ($showListingKind)
            <p class="mb-2">
                <span class="badge badge-secondary badge-sm">{{ __('ui.browse.listing_kind_activity') }}</span>
            </p>
        @endif

        <div class="flex items-start justify-between gap-2">
            <h3 class="card-title text-xl leading-tight">
                <a href="{{ route('activities.show', $activity) }}" wire:navigate class="link link-primary ui-link ui-link-title" data-ui="activity-card-title-link">{{ $activity->name }}</a>
            </h3>
            @auth
                <div class="shrink-0">
                    @if (in_array($activity->id, $interestedActivityIds))
                        <form action="{{ route('interests.activities.remove', $activity) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" class="btn-ghost btn-sm text-warning ui-action ui-action-interest-remove" :title="__('ui.interests.remove_from_interests')" data-ui="activity-card-interest-remove">★</x-button>
                        </form>
                    @else
                        <form action="{{ route('interests.activities.add', $activity) }}" method="POST" class="inline">
                            @csrf
                            <x-button type="submit" class="btn-ghost btn-sm ui-action ui-action-interest-add" :title="__('ui.interests.add_to_interests')" data-ui="activity-card-interest-add">☆</x-button>
                        </form>
                    @endif
                </div>
            @endauth
        </div>

        @if ($activity->creator)
            <p class="text-sm opacity-70">{{ __('Host') }}: {{ $activity->creator->nickname ?? $activity->creator->email }}</p>
        @endif

        @if ($activity->slot && $activity->slot->event)
            <p class="text-sm opacity-70">
                {{ __('ui.browse.attached_event') }}:
                <a href="{{ route('events.show', $activity->slot->event) }}" wire:navigate class="link link-hover">{{ $activity->slot->event->name }}</a>
            </p>
        @endif

        @if ($durationLabel)
            <p class="text-sm opacity-70">{{ __('ui.browse.duration_label') }}: {{ $durationLabel }}</p>
        @endif

        <p class="text-sm tabular-nums opacity-80">
            {{ __('ui.browse.participants_count') }}:
            @if ($max !== null)
                {{ __('ui.browse.participants_filled_max', ['filled' => $filled, 'max' => $max]) }}
            @else
                {{ __('ui.browse.participants_filled_no_cap', ['filled' => $filled]) }}
            @endif
        </p>

        <x-ui.activity-badge-group :items="$activityBadgeItems" data-ui="activity-card-badge-group" />
    </div>
</article>
