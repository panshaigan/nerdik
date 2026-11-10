@php
    /** @var \Illuminate\Support\Collection<int, array{kind: string, event?: mixed, activity?: mixed, starts_at: mixed}> $thisWeekItems */
@endphp

@if ($thisWeekItems->isNotEmpty())
    <section class="space-y-4" data-ui="dashboard-this-week">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-base-content/55">
            {{ __('ui.dashboard.this_week') }}
        </h2>
        <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
            @foreach ($thisWeekItems as $row)
                <x-cards.listing-card
                    :listing="$row['kind'] === 'event' ? $row['event'] : $row['activity']"
                    :interested-ids="$row['kind'] === 'event' ? ($interestedEventIds ?? []) : ($interestedActivityIds ?? [])"
                    :return-url="$browsingReturnUrl"
                    :confirmed-activities-count="$row['kind'] === 'event' ? ($eventProgrammeActivityCounts[$row['event']->id] ?? 0) : null"
                />
            @endforeach
        </div>
    </section>
@endif
