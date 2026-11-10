<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DashboardHostChoreService
{
    private const int SOURCE_LIMIT = 2;

    private const int MARK_ABSENCES_LOOKBACK_DAYS = 7;

    private const int SERIES_LOOKBACK_DAYS = 14;

    public function __construct(
        private readonly UpcomingFeedQueryService $upcomingFeedQuery,
    ) {}

    /**
     * @return Collection<int, DashboardActionItem>
     */
    public function choresFor(User $user): Collection
    {
        return collect()
            ->concat($this->lowParticipationWarnings($user))
            ->concat($this->markAbsencesReminders($user))
            ->concat($this->nextEditionReminders($user));
    }

    /**
     * @return Collection<int, DashboardActionItem>
     */
    private function lowParticipationWarnings(User $user): Collection
    {
        $runwayDays = max(0, (int) config('scheduled_notifications.organizer_low_participation_runway_days', 7));
        $referenceNow = now();

        $organizerItems = Activity::query()
            ->whereNotNull('min_participants')
            ->whereNull('cancelled_at')
            ->whereHas('slot.event', function ($query) use ($user): void {
                $query
                    ->where('created_by', $user->id)
                    ->whereNull('cancelled_at')
                    ->whereNotNull('starts_at');
            })
            ->with(['slot.event'])
            ->withCount(['participants as active_participants_count' => fn ($query) => $query->where('is_absent', false)])
            ->get()
            ->map(function (Activity $activity) use ($user, $referenceNow, $runwayDays): ?DashboardActionItem {
                return $this->mapLowParticipationItem($activity, $user, $referenceNow, $runwayDays, true);
            })
            ->filter();

        $hostItems = Activity::query()
            ->where('created_by', $user->id)
            ->whereNotNull('min_participants')
            ->whereNull('cancelled_at')
            ->tap(fn ($query) => $this->upcomingFeedQuery->constrainToUpcomingActivities($query))
            ->with('slot')
            ->withCount(['participants as active_participants_count' => fn ($query) => $query->where('is_absent', false)])
            ->get()
            ->map(function (Activity $activity) use ($user, $referenceNow, $runwayDays): ?DashboardActionItem {
                return $this->mapLowParticipationItem($activity, $user, $referenceNow, $runwayDays, false);
            })
            ->filter();

        return $organizerItems->concat($hostItems)->take(self::SOURCE_LIMIT)->values();
    }

    private function mapLowParticipationItem(
        Activity $activity,
        User $user,
        CarbonInterface $referenceNow,
        int $runwayDays,
        bool $useEventStart,
    ): ?DashboardActionItem {
        $minimum = (int) ($activity->min_participants ?? 0);
        $current = (int) ($activity->active_participants_count ?? 0);
        if ($minimum <= 0 || $current >= $minimum) {
            return null;
        }

        $target = $useEventStart
            ? ($activity->slot?->event?->starts_at ?? null)
            : ($activity->slot?->starts_at ?? $activity->starts_at);

        if ($target === null || ! $this->isWithinLocalDaysAhead($referenceNow, $target, $user, $runwayDays)) {
            return null;
        }

        return new DashboardActionItem(
            kind: 'low_participation',
            title: __('ui.dashboard.actions.low_participation'),
            url: route('activities.show', ['activity' => $activity]),
            meta: __('ui.dashboard.actions.low_participation_meta', [
                'activity' => (string) $activity->name,
                'current' => $current,
                'minimum' => $minimum,
            ]),
            icon: 'o-exclamation-triangle',
        );
    }

    /**
     * @return Collection<int, DashboardActionItem>
     */
    private function markAbsencesReminders(User $user): Collection
    {
        $timezone = $this->timezoneForUser($user);
        $localNow = now()->setTimezone($timezone);
        $earliest = $localNow->copy()->subDays(self::MARK_ABSENCES_LOOKBACK_DAYS)->startOfDay();

        return Activity::query()
            ->where('created_by', $user->id)
            ->whereNull('cancelled_at')
            ->whereHas('participants', fn ($query) => $query->where('is_absent', false))
            ->with('slot')
            ->withCount(['participants as unmarked_participants_count' => fn ($query) => $query->where('is_absent', false)])
            ->get()
            ->map(function (Activity $activity) use ($user, $earliest, $localNow): ?DashboardActionItem {
                $endedAt = $this->activityEndedAt($activity);
                if ($endedAt === null) {
                    return null;
                }

                $localEnd = $endedAt->copy()->setTimezone($this->timezoneForUser($user));
                if ($localEnd->greaterThan($localNow) || $localEnd->lessThan($earliest)) {
                    return null;
                }

                $unmarked = (int) ($activity->unmarked_participants_count ?? 0);
                if ($unmarked < 1) {
                    return null;
                }

                return new DashboardActionItem(
                    kind: 'mark_absences',
                    title: __('ui.dashboard.actions.mark_absences'),
                    url: route('activities.show', ['activity' => $activity]),
                    meta: __('ui.dashboard.actions.mark_absences_meta', [
                        'activity' => (string) $activity->name,
                        'count' => $unmarked,
                    ]),
                    icon: 'o-clipboard-document-check',
                );
            })
            ->filter()
            ->take(self::SOURCE_LIMIT)
            ->values();
    }

    /**
     * @return Collection<int, DashboardActionItem>
     */
    private function nextEditionReminders(User $user): Collection
    {
        $referenceNow = now();
        $proposedNextEventIds = ActivityProposal::query()
            ->where('created_by', $user->id)
            ->pluck('event_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $followedEventIds = $user->interestedEvents()
            ->pluck('events.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $hostItems = Activity::query()
            ->where('created_by', $user->id)
            ->whereNull('cancelled_at')
            ->whereHas('slot.event', fn ($query) => $query->whereNotNull('event_series_id')->whereNull('cancelled_at'))
            ->with(['slot.event'])
            ->get()
            ->map(function (Activity $activity) use ($user, $referenceNow, $proposedNextEventIds): ?DashboardActionItem {
                $pastEvent = $activity->slot?->event;
                if ($pastEvent === null) {
                    return null;
                }

                return $this->mapProposeNextEdition($pastEvent, $user, $referenceNow, $proposedNextEventIds);
            })
            ->filter()
            ->unique(fn (DashboardActionItem $item) => $item->url)
            ->take(1);

        $participantItems = Activity::query()
            ->whereNull('cancelled_at')
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id)->where('is_absent', false))
            ->whereHas('slot.event', fn ($query) => $query->whereNotNull('event_series_id')->whereNull('cancelled_at'))
            ->with(['slot.event'])
            ->get()
            ->map(function (Activity $activity) use ($user, $referenceNow, $followedEventIds): ?DashboardActionItem {
                $pastEvent = $activity->slot?->event;
                if ($pastEvent === null) {
                    return null;
                }

                return $this->mapFollowNextEdition($pastEvent, $user, $referenceNow, $followedEventIds);
            })
            ->filter()
            ->unique(fn (DashboardActionItem $item) => $item->url)
            ->take(1);

        return $hostItems->concat($participantItems)->values();
    }

    /**
     * @param  list<int>  $proposedNextEventIds
     */
    private function mapProposeNextEdition(
        Event $pastEvent,
        User $user,
        CarbonInterface $referenceNow,
        array $proposedNextEventIds,
    ): ?DashboardActionItem {
        $eventEnd = $this->eventEndedAt($pastEvent);
        if ($eventEnd === null || ! $this->endedWithinLookback($referenceNow, $eventEnd, $user, self::SERIES_LOOKBACK_DAYS)) {
            return null;
        }

        $nextEvent = $pastEvent->nextInSeries();
        if ($nextEvent === null || $nextEvent->cancelled_at !== null || in_array((int) $nextEvent->id, $proposedNextEventIds, true)) {
            return null;
        }

        return new DashboardActionItem(
            kind: 'propose_next_edition',
            title: __('ui.dashboard.actions.propose_next_edition'),
            url: route('events.propose', ['event' => $nextEvent]),
            meta: __('ui.dashboard.actions.propose_next_edition_meta', [
                'next_event' => (string) $nextEvent->name,
            ]),
            icon: 'o-arrow-path',
        );
    }

    /**
     * @param  list<int>  $followedEventIds
     */
    private function mapFollowNextEdition(
        Event $pastEvent,
        User $user,
        CarbonInterface $referenceNow,
        array $followedEventIds,
    ): ?DashboardActionItem {
        $eventEnd = $this->eventEndedAt($pastEvent);
        if ($eventEnd === null || ! $this->endedWithinLookback($referenceNow, $eventEnd, $user, self::SERIES_LOOKBACK_DAYS)) {
            return null;
        }

        $nextEvent = $pastEvent->nextInSeries();
        if ($nextEvent === null || $nextEvent->cancelled_at !== null || in_array((int) $nextEvent->id, $followedEventIds, true)) {
            return null;
        }

        return new DashboardActionItem(
            kind: 'follow_next_edition',
            title: __('ui.dashboard.actions.follow_next_edition'),
            url: route('events.show', ['event' => $nextEvent]),
            meta: __('ui.dashboard.actions.follow_next_edition_meta', [
                'next_event' => (string) $nextEvent->name,
            ]),
            icon: 'o-heart',
        );
    }

    private function activityEndedAt(Activity $activity): ?Carbon
    {
        $end = $activity->slot?->ends_at
            ?? $activity->ends_at
            ?? $activity->slot?->starts_at
            ?? $activity->starts_at;

        return $end !== null ? Carbon::parse($end) : null;
    }

    private function eventEndedAt(Event $event): ?Carbon
    {
        $end = $event->ends_at ?? $event->starts_at;

        return $end !== null ? Carbon::parse($end) : null;
    }

    private function isWithinLocalDaysAhead(CarbonInterface $referenceNow, CarbonInterface $target, User $user, int $daysAhead): bool
    {
        if ($daysAhead < 0 || $target->lessThanOrEqualTo($referenceNow)) {
            return false;
        }

        $timezone = $this->timezoneForUser($user);
        $localToday = $referenceNow->copy()->setTimezone($timezone)->startOfDay();
        $localTarget = $target->copy()->setTimezone($timezone)->startOfDay();
        $latest = $localToday->copy()->addDays($daysAhead);

        return $localTarget->greaterThanOrEqualTo($localToday)
            && $localTarget->lessThanOrEqualTo($latest);
    }

    private function endedWithinLookback(CarbonInterface $referenceNow, CarbonInterface $endedAt, User $user, int $days): bool
    {
        $timezone = $this->timezoneForUser($user);
        $localNow = $referenceNow->copy()->setTimezone($timezone);
        $localEnd = $endedAt->copy()->setTimezone($timezone);
        $earliest = $localNow->copy()->subDays($days)->startOfDay();

        return $localEnd->lessThanOrEqualTo($localNow) && $localEnd->greaterThanOrEqualTo($earliest);
    }

    private function timezoneForUser(User $user): string
    {
        $name = $user->timezone ?: (string) config('scheduled_notifications.timezone_fallback', 'UTC');

        try {
            new \DateTimeZone($name);

            return $name;
        } catch (\Throwable) {
            return (string) config('app.timezone', 'UTC');
        }
    }
}
