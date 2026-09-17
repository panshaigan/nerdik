<?php

namespace App\Services\Notifications\Scheduled;

use App\Enums\NotificationPreferenceKey;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\User;
use App\Services\Dashboard\UpcomingFeedQueryService;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;

class ScheduledNotificationCollector
{
    public function __construct(
        private readonly UpcomingFeedQueryService $upcomingFeedQueryService
    ) {}

    /**
     * @return list<array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    public function collectForUser(User $user, CarbonImmutable $referenceNow): array
    {
        $items = collect();

        if ($this->wantsScheduledCategory($user, NotificationPreferenceKey::ScheduledInterestedEnrollmentWindow)) {
            $items = $items->concat($this->collectInterestedEnrollmentWindows($user, $referenceNow));
        }

        if ($this->wantsScheduledCategory($user, NotificationPreferenceKey::ScheduledDashboardFeed)) {
            $items = $items->concat($this->collectDashboardFeedItems($user, $referenceNow));
        }

        if ($this->wantsScheduledCategory($user, NotificationPreferenceKey::ScheduledParticipantCancellationDeadline)) {
            $items = $items->concat($this->collectParticipantCancellationDeadlines($user, $referenceNow));
        }

        if ($this->wantsScheduledCategory($user, NotificationPreferenceKey::ScheduledOrganizerLowParticipation)) {
            $items = $items->concat($this->collectOrganizerLowParticipationWarnings($user, $referenceNow));
        }

        if ($this->wantsScheduledCategory($user, NotificationPreferenceKey::ScheduledHostLowParticipation)) {
            $items = $items->concat($this->collectHostLowParticipationWarnings($user, $referenceNow));
        }

        if ($this->wantsScheduledCategory($user, NotificationPreferenceKey::ScheduledHostMarkAbsences)) {
            $items = $items->concat($this->collectHostUpcomingMarkAbsencesReminders($user, $referenceNow));
            $items = $items->concat($this->collectHostMarkAbsencesReminders($user, $referenceNow));
        }

        if ($this->wantsScheduledCategory($user, NotificationPreferenceKey::ScheduledSeriesNextEdition)) {
            $items = $items->concat($this->collectHostProposeNextEditionReminders($user, $referenceNow));
            $items = $items->concat($this->collectParticipantFollowNextEditionReminders($user, $referenceNow));
        }

        return $items->values()->all();
    }

    private function wantsScheduledCategory(User $user, NotificationPreferenceKey $key): bool
    {
        return $user->wantsNotificationChannel($key, 'in_app')
            || $user->wantsNotificationChannel($key, 'email');
    }

    /**
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectInterestedEnrollmentWindows(User $user, CarbonImmutable $referenceNow): Collection
    {
        $timezone = $this->timezoneForUser($user);

        return $user->interestedEvents()
            ->with(['enrollmentWindows' => fn ($query) => $query->orderBy('starts_at')])
            ->get()
            ->map(function (Event $event) use ($referenceNow, $timezone): ?array {
                /** @var EventEnrollmentWindow|null $nextWindow */
                $nextWindow = $event->enrollmentWindows
                    ->first(fn (EventEnrollmentWindow $window): bool => $window->starts_at !== null && $window->starts_at->greaterThanOrEqualTo($referenceNow));

                if ($nextWindow?->starts_at === null) {
                    return null;
                }

                $windowStart = CarbonImmutable::instance($nextWindow->starts_at);
                if (! $this->isWithinLookahead($referenceNow, $windowStart)) {
                    return null;
                }

                return [
                    'category' => 'interested_enrollment_window',
                    'title' => __('ui.notifications.scheduled.enrollment_window_title', ['event' => (string) $event->name]),
                    'lines' => [
                        __('ui.notifications.scheduled.enrollment_window_line', [
                            'when' => $windowStart->setTimezone($timezone)->format('Y-m-d H:i'),
                            'hours' => (int) $referenceNow->diffInHours($windowStart),
                        ]),
                    ],
                    'url' => route('events.show', ['event' => $event], false),
                    'dedupe_key' => $this->dedupeKey('interested_enrollment_window', (int) $event->id, $windowStart),
                ];
            })
            ->filter();
    }

    /**
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectDashboardFeedItems(User $user, CarbonImmutable $referenceNow): Collection
    {
        $rows = $this->upcomingFeedQueryService->dedupeAndSort(
            $this->upcomingFeedQueryService->buildUnifiedUpcomingRows((int) $user->id)
        );

        $timezone = $this->timezoneForUser($user);

        $dueRows = $rows
            ->map(function (array $row): array {
                $sortAt = CarbonImmutable::parse((string) $row['sort_at'], 'UTC');

                return [
                    'kind' => (string) $row['kind'],
                    'id' => (int) $row['id'],
                    'sort_at' => $sortAt,
                ];
            })
            ->filter(fn (array $row): bool => $this->isOnLocalTomorrow($referenceNow, $row['sort_at'], $user))
            ->values();

        if ($dueRows->isEmpty()) {
            return collect();
        }

        $eventIds = $dueRows->where('kind', 'event')->pluck('id')->all();
        $activityIds = $dueRows->where('kind', 'activity')->pluck('id')->all();

        $events = Event::query()->whereKey($eventIds)->get()->keyBy('id');
        $activities = Activity::query()->whereKey($activityIds)->get()->keyBy('id');

        $lines = $dueRows->map(function (array $row) use ($events, $activities, $timezone): string {
            $when = $row['sort_at']->setTimezone($timezone)->format('Y-m-d H:i');

            if ($row['kind'] === 'event') {
                /** @var Event|null $event */
                $event = $events->get($row['id']);

                return __('ui.notifications.scheduled.dashboard_feed_event_line', [
                    'name' => (string) ($event?->name ?? '—'),
                    'when' => $when,
                ]);
            }

            /** @var Activity|null $activity */
            $activity = $activities->get($row['id']);

            return __('ui.notifications.scheduled.dashboard_feed_activity_line', [
                'name' => (string) ($activity?->name ?? '—'),
                'when' => $when,
            ]);
        })->all();

        $tomorrowStart = $referenceNow->setTimezone($timezone)->addDay()->startOfDay()->utc();

        return collect([[
            'category' => 'dashboard_feed',
            'title' => __('ui.notifications.scheduled.dashboard_feed_title', ['count' => count($lines)]),
            'lines' => $lines,
            'url' => route('dashboard'),
            'dedupe_key' => $this->dedupeKey('dashboard_feed', (int) $user->id, $tomorrowStart),
        ]]);
    }

    /**
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectParticipantCancellationDeadlines(User $user, CarbonImmutable $referenceNow): Collection
    {
        return Activity::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id)->where('is_absent', false))
            ->whereNotNull('cancellation_deadline_in_hours')
            ->whereNull('cancelled_at')
            ->with('slot')
            ->get()
            ->map(function (Activity $activity) use ($user, $referenceNow): ?array {
                $deadline = $this->cancellationDeadlineAt($activity);
                if ($deadline === null || ! $this->isOnLocalTomorrow($referenceNow, $deadline, $user)) {
                    return null;
                }

                return [
                    'category' => 'participant_cancellation_deadline',
                    'title' => __('ui.notifications.scheduled.participant_deadline_title', ['activity' => (string) $activity->name]),
                    'lines' => [
                        __('ui.notifications.scheduled.participant_deadline_line', [
                            'when' => $deadline->setTimezone($this->timezoneForUser($user))->format('Y-m-d H:i'),
                        ]),
                    ],
                    'url' => route('activities.show', ['activity' => $activity], false),
                    'dedupe_key' => $this->dedupeKey('participant_cancellation_deadline', (int) $activity->id, $deadline),
                ];
            })
            ->filter();
    }

    /**
     * Event organizer digest: daily while the event's local start is within the
     * runway and any slotted activity is under min_participants.
     *
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectOrganizerLowParticipationWarnings(User $user, CarbonImmutable $referenceNow): Collection
    {
        $runwayDays = max(0, (int) config('scheduled_notifications.organizer_low_participation_runway_days', 7));

        return Activity::query()
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
            ->map(function (Activity $activity) use ($user, $referenceNow, $runwayDays): ?array {
                $minimum = (int) ($activity->min_participants ?? 0);
                $current = (int) ($activity->active_participants_count ?? 0);
                if ($minimum <= 0 || $current >= $minimum) {
                    return null;
                }

                $event = $activity->slot?->event;
                $eventStart = $event?->starts_at !== null
                    ? CarbonImmutable::instance($event->starts_at)
                    : null;

                if ($eventStart === null || ! $this->isWithinLocalDaysAhead($referenceNow, $eventStart, $user, $runwayDays)) {
                    return null;
                }

                $timezone = $this->timezoneForUser($user);
                $localDay = $referenceNow->setTimezone($timezone)->startOfDay()->utc();

                return [
                    'category' => 'organizer_low_participation',
                    'title' => __('ui.notifications.scheduled.organizer_low_participation_title', [
                        'activity' => (string) $activity->name,
                        'event' => (string) ($event->name ?? ''),
                    ]),
                    'lines' => [
                        __('ui.notifications.scheduled.organizer_low_participation_line', [
                            'current' => $current,
                            'minimum' => $minimum,
                            'when' => $eventStart->setTimezone($timezone)->format('Y-m-d H:i'),
                        ]),
                    ],
                    'url' => route('activities.show', ['activity' => $activity], false),
                    'dedupe_key' => $this->dedupeKey('organizer_low_participation', (int) $activity->id, $localDay),
                ];
            })
            ->filter();
    }

    /**
     * Activity host digest: only on configured local calendar offsets before the
     * cancellation deadline (default 3 and 1 days), while under min_participants.
     *
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectHostLowParticipationWarnings(User $user, CarbonImmutable $referenceNow): Collection
    {
        /** @var list<int> $deadlineOffsets */
        $deadlineOffsets = array_values(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, (array) config(
                'scheduled_notifications.host_low_participation_deadline_offsets',
                [3, 1]
            )),
            static fn (int $days): bool => $days >= 1
        ));

        if ($deadlineOffsets === []) {
            return collect();
        }

        return Activity::query()
            ->where('created_by', $user->id)
            ->whereNotNull('min_participants')
            ->whereNotNull('cancellation_deadline_in_hours')
            ->whereNull('cancelled_at')
            ->with('slot')
            ->withCount(['participants as active_participants_count' => fn ($query) => $query->where('is_absent', false)])
            ->get()
            ->map(function (Activity $activity) use ($user, $referenceNow, $deadlineOffsets): ?array {
                $minimum = (int) ($activity->min_participants ?? 0);
                $current = (int) ($activity->active_participants_count ?? 0);
                if ($minimum <= 0 || $current >= $minimum) {
                    return null;
                }

                $deadline = $this->cancellationDeadlineAt($activity);
                if ($deadline === null || ! $this->isOnAnyLocalDayOffset($referenceNow, $deadline, $user, $deadlineOffsets)) {
                    return null;
                }

                $timezone = $this->timezoneForUser($user);
                $localDay = $referenceNow->setTimezone($timezone)->startOfDay()->utc();

                return [
                    'category' => 'host_low_participation',
                    'title' => __('ui.notifications.scheduled.host_low_participation_title', ['activity' => (string) $activity->name]),
                    'lines' => [
                        __('ui.notifications.scheduled.host_low_participation_line', [
                            'current' => $current,
                            'minimum' => $minimum,
                            'when' => $deadline->setTimezone($timezone)->format('Y-m-d H:i'),
                        ]),
                    ],
                    'url' => route('activities.show', ['activity' => $activity], false),
                    'dedupe_key' => $this->dedupeKey('host_low_participation', (int) $activity->id, $localDay),
                ];
            })
            ->filter();
    }

    /**
     * Day-before heads-up: after tomorrow's hosted activity, the host will be asked to mark absences.
     *
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectHostUpcomingMarkAbsencesReminders(User $user, CarbonImmutable $referenceNow): Collection
    {
        return Activity::query()
            ->where('created_by', $user->id)
            ->whereNull('cancelled_at')
            ->whereHas('participants', fn ($query) => $query->where('is_absent', false))
            ->with('slot')
            ->get()
            ->map(function (Activity $activity) use ($user, $referenceNow): ?array {
                $activityStart = $this->activityStartedAt($activity);
                if ($activityStart === null || ! $this->isOnLocalTomorrow($referenceNow, $activityStart, $user)) {
                    return null;
                }

                return [
                    'category' => 'host_upcoming_mark_absences',
                    'title' => __('ui.notifications.scheduled.host_upcoming_mark_absences_title', ['activity' => (string) $activity->name]),
                    'lines' => [
                        __('ui.notifications.scheduled.host_upcoming_mark_absences_line', [
                            'when' => $activityStart->setTimezone($this->timezoneForUser($user))->format('Y-m-d H:i'),
                        ]),
                    ],
                    'url' => route('activities.show', ['activity' => $activity], false),
                    'dedupe_key' => $this->dedupeKey('host_upcoming_mark_absences', (int) $activity->id, $activityStart),
                ];
            })
            ->filter();
    }

    /**
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectHostMarkAbsencesReminders(User $user, CarbonImmutable $referenceNow): Collection
    {
        return Activity::query()
            ->where('created_by', $user->id)
            ->whereNull('cancelled_at')
            ->whereHas('participants', fn ($query) => $query->where('is_absent', false))
            ->with('slot')
            ->withCount(['participants as unmarked_participants_count' => fn ($query) => $query->where('is_absent', false)])
            ->get()
            ->map(function (Activity $activity) use ($user, $referenceNow): ?array {
                $activityEnd = $this->activityEndedAt($activity);
                if ($activityEnd === null || ! $this->isOnLocalYesterday($referenceNow, $activityEnd, $user)) {
                    return null;
                }

                $unmarkedCount = (int) ($activity->unmarked_participants_count ?? 0);
                if ($unmarkedCount < 1) {
                    return null;
                }

                return [
                    'category' => 'host_mark_absences',
                    'title' => __('ui.notifications.scheduled.host_mark_absences_title', ['activity' => (string) $activity->name]),
                    'lines' => [
                        __('ui.notifications.scheduled.host_mark_absences_line', [
                            'count' => $unmarkedCount,
                        ]),
                    ],
                    'url' => route('activities.show', ['activity' => $activity], false),
                    'dedupe_key' => $this->dedupeKey('host_mark_absences', (int) $activity->id, $activityEnd),
                ];
            })
            ->filter();
    }

    /**
     * Day after a series event ends: thank activity hosts and nudge them to propose for the next edition.
     *
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectHostProposeNextEditionReminders(User $user, CarbonImmutable $referenceNow): Collection
    {
        $proposedNextEventIds = ActivityProposal::query()
            ->where('created_by', $user->id)
            ->pluck('event_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return Activity::query()
            ->where('created_by', $user->id)
            ->whereNull('cancelled_at')
            ->whereHas('slot.event', function ($query): void {
                $query
                    ->whereNull('cancelled_at')
                    ->whereNotNull('event_series_id');
            })
            ->with(['slot.event'])
            ->get()
            ->map(function (Activity $activity) use ($user, $referenceNow, $proposedNextEventIds): ?array {
                $pastEvent = $activity->slot?->event;
                if ($pastEvent === null) {
                    return null;
                }

                $eventEnd = $this->eventEndedAt($pastEvent);
                if ($eventEnd === null || ! $this->isOnLocalYesterday($referenceNow, $eventEnd, $user)) {
                    return null;
                }

                $nextEvent = $this->nextNonCancelledEdition($pastEvent);
                if ($nextEvent === null || in_array((int) $nextEvent->id, $proposedNextEventIds, true)) {
                    return null;
                }

                return [
                    'category' => 'host_propose_next_edition',
                    'title' => __('ui.notifications.scheduled.host_propose_next_edition_title', [
                        'past_event' => (string) $pastEvent->name,
                    ]),
                    'lines' => [
                        __('ui.notifications.scheduled.host_propose_next_edition_line', [
                            'next_event' => (string) $nextEvent->name,
                        ]),
                    ],
                    'url' => route('events.propose', ['event' => $nextEvent], false),
                    'dedupe_key' => $this->dedupeKey('host_propose_next_edition', (int) $pastEvent->id, $eventEnd),
                    '_past_event_id' => (int) $pastEvent->id,
                ];
            })
            ->filter()
            ->unique('_past_event_id')
            ->map(function (array $item): array {
                unset($item['_past_event_id']);

                return $item;
            })
            ->values();
    }

    /**
     * Day after a series event ends: thank non-absent participants and nudge them to follow the next edition.
     *
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectParticipantFollowNextEditionReminders(User $user, CarbonImmutable $referenceNow): Collection
    {
        $followedEventIds = $user->interestedEvents()
            ->pluck('events.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return Activity::query()
            ->whereNull('cancelled_at')
            ->whereHas('participants', function ($query) use ($user): void {
                $query
                    ->where('user_id', $user->id)
                    ->where('is_absent', false);
            })
            ->whereHas('slot.event', function ($query): void {
                $query
                    ->whereNull('cancelled_at')
                    ->whereNotNull('event_series_id');
            })
            ->with(['slot.event'])
            ->get()
            ->map(function (Activity $activity) use ($user, $referenceNow, $followedEventIds): ?array {
                $pastEvent = $activity->slot?->event;
                if ($pastEvent === null) {
                    return null;
                }

                $eventEnd = $this->eventEndedAt($pastEvent);
                if ($eventEnd === null || ! $this->isOnLocalYesterday($referenceNow, $eventEnd, $user)) {
                    return null;
                }

                $nextEvent = $this->nextNonCancelledEdition($pastEvent);
                if ($nextEvent === null || in_array((int) $nextEvent->id, $followedEventIds, true)) {
                    return null;
                }

                return [
                    'category' => 'participant_follow_next_edition',
                    'title' => __('ui.notifications.scheduled.participant_follow_next_edition_title', [
                        'past_event' => (string) $pastEvent->name,
                    ]),
                    'lines' => [
                        __('ui.notifications.scheduled.participant_follow_next_edition_line', [
                            'next_event' => (string) $nextEvent->name,
                        ]),
                    ],
                    'url' => route('events.show', ['event' => $nextEvent], false),
                    'dedupe_key' => $this->dedupeKey('participant_follow_next_edition', (int) $pastEvent->id, $eventEnd),
                    '_past_event_id' => (int) $pastEvent->id,
                ];
            })
            ->filter()
            ->unique('_past_event_id')
            ->map(function (array $item): array {
                unset($item['_past_event_id']);

                return $item;
            })
            ->values();
    }

    private function eventEndedAt(Event $event): ?CarbonImmutable
    {
        $end = $event->ends_at ?? $event->starts_at;

        if ($end === null) {
            return null;
        }

        return CarbonImmutable::instance($end);
    }

    private function nextNonCancelledEdition(Event $event): ?Event
    {
        $next = $event->nextInSeries();

        if ($next === null || $next->cancelled_at !== null) {
            return null;
        }

        return $next;
    }

    private function activityStartedAt(Activity $activity): ?CarbonImmutable
    {
        $start = $activity->slot?->starts_at ?? $activity->starts_at;

        if ($start === null) {
            return null;
        }

        return CarbonImmutable::instance($start);
    }

    private function activityEndedAt(Activity $activity): ?CarbonImmutable
    {
        $end = $activity->slot?->ends_at
            ?? $activity->ends_at
            ?? $activity->slot?->starts_at
            ?? $activity->starts_at;

        if ($end === null) {
            return null;
        }

        return CarbonImmutable::instance($end);
    }

    private function cancellationDeadlineAt(Activity $activity): ?CarbonImmutable
    {
        $activityStart = $activity->slot?->starts_at ?? $activity->starts_at;
        if ($activityStart === null || $activity->cancellation_deadline_in_hours === null) {
            return null;
        }

        return CarbonImmutable::instance($activityStart)
            ->subHours((int) $activity->cancellation_deadline_in_hours);
    }

    private function isWithinLookahead(CarbonImmutable $referenceNow, CarbonImmutable $target): bool
    {
        $hours = (int) config('scheduled_notifications.lookahead_hours', 24);

        return $target->greaterThan($referenceNow)
            && $target->lessThanOrEqualTo($referenceNow->addHours($hours));
    }

    /**
     * Digests that target a calendar day (dashboard feed, cancellation deadlines,
     * host upcoming absences) fire on the local day before the target timestamp.
     */
    private function isOnLocalTomorrow(CarbonImmutable $referenceNow, CarbonImmutable $target, User $user): bool
    {
        return $this->isOnLocalDayOffset($referenceNow, $target, $user, 1);
    }

    /**
     * True when the target's local calendar date equals today + $daysAhead for the user.
     */
    private function isOnLocalDayOffset(CarbonImmutable $referenceNow, CarbonImmutable $target, User $user, int $daysAhead): bool
    {
        if ($daysAhead < 1 || $target->lessThanOrEqualTo($referenceNow)) {
            return false;
        }

        $timezone = $this->timezoneForUser($user);
        $expectedDate = $referenceNow->setTimezone($timezone)->addDays($daysAhead)->toDateString();

        return $target->setTimezone($timezone)->toDateString() === $expectedDate;
    }

    /**
     * @param  list<int>  $daysAheadOffsets
     */
    private function isOnAnyLocalDayOffset(CarbonImmutable $referenceNow, CarbonImmutable $target, User $user, array $daysAheadOffsets): bool
    {
        foreach ($daysAheadOffsets as $daysAhead) {
            if ($this->isOnLocalDayOffset($referenceNow, $target, $user, $daysAhead)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the target is still in the future and its local calendar date is
     * between the user's local today and today + $daysAhead (inclusive).
     */
    private function isWithinLocalDaysAhead(CarbonImmutable $referenceNow, CarbonImmutable $target, User $user, int $daysAhead): bool
    {
        if ($daysAhead < 0 || $target->lessThanOrEqualTo($referenceNow)) {
            return false;
        }

        $timezone = $this->timezoneForUser($user);
        $localToday = $referenceNow->setTimezone($timezone)->startOfDay();
        $localTarget = $target->setTimezone($timezone)->startOfDay();
        $latest = $localToday->addDays($daysAhead);

        return $localTarget->greaterThanOrEqualTo($localToday)
            && $localTarget->lessThanOrEqualTo($latest);
    }

    /**
     * Host absence reminders fire on the local calendar day after the activity ended.
     */
    private function isOnLocalYesterday(CarbonImmutable $referenceNow, CarbonImmutable $target, User $user): bool
    {
        $timezone = $this->timezoneForUser($user);
        $localYesterday = $referenceNow->setTimezone($timezone)->subDay()->toDateString();

        return $target->setTimezone($timezone)->toDateString() === $localYesterday;
    }

    private function timezoneForUser(User $user): DateTimeZone
    {
        $name = $user->timezone ?: (string) config('scheduled_notifications.timezone_fallback', 'UTC');

        try {
            return new DateTimeZone($name);
        } catch (\Throwable) {
            return new DateTimeZone((string) config('app.timezone', 'UTC'));
        }
    }

    private function dedupeKey(string $type, int $entityId, CarbonImmutable $target): string
    {
        return sprintf('%s:%d:%s', $type, $entityId, $target->utc()->format('Y-m-d\TH:i:s\Z'));
    }
}
