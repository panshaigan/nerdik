<?php

namespace App\Services\Notifications\Scheduled;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
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
        $timezone = $this->timezoneForUser($user);
        $localNow = $referenceNow->setTimezone($timezone);

        return collect()
            ->concat($this->collectOrganizerUnansweredProposals($user, $referenceNow, $localNow, $timezone))
            ->concat($this->collectInterestedEnrollmentWindows($user, $referenceNow, $localNow, $timezone))
            ->concat($this->collectDashboardFeedItems($user, $referenceNow, $localNow, $timezone))
            ->concat($this->collectParticipantCancellationDeadlines($user, $referenceNow, $localNow, $timezone))
            ->concat($this->collectHostLowParticipationWarnings($user, $referenceNow, $localNow, $timezone))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectOrganizerUnansweredProposals(User $user, CarbonImmutable $referenceNow, CarbonImmutable $localNow, DateTimeZone $timezone): Collection
    {
        $baselineWeekdays = collect(config('scheduled_notifications.organizer_unanswered_proposals.baseline_weekdays', [1, 4]))
            ->map(fn ($weekday) => (int) $weekday)
            ->all();
        $escalationWindowDays = (int) config('scheduled_notifications.organizer_unanswered_proposals.daily_escalation_window_days', 7);

        return Event::query()
            ->where('created_by', $user->id)
            ->withCount([
                'proposals as pending_proposals_count' => fn ($query) => $query->where('status', ActivityProposalStatus::Pending),
            ])
            ->whereHas('proposals', fn ($query) => $query->where('status', ActivityProposalStatus::Pending))
            ->with(['enrollmentWindows' => fn ($query) => $query->orderBy('starts_at')])
            ->get()
            ->map(function (Event $event) use ($baselineWeekdays, $escalationWindowDays, $referenceNow, $localNow, $timezone): ?array {
                /** @var EventEnrollmentWindow|null $nextWindow */
                $nextWindow = $event->enrollmentWindows
                    ->first(fn (EventEnrollmentWindow $window): bool => $window->starts_at !== null && $window->starts_at->greaterThanOrEqualTo($referenceNow));

                $isEscalated = false;
                if ($nextWindow?->starts_at !== null) {
                    $daysUntilWindow = $this->daysUntil($localNow, CarbonImmutable::instance($nextWindow->starts_at)->setTimezone($timezone));
                    $isEscalated = $daysUntilWindow >= 0 && $daysUntilWindow <= $escalationWindowDays;
                }

                $shouldSend = $isEscalated || in_array((int) $localNow->dayOfWeekIso, $baselineWeekdays, true);
                if (! $shouldSend) {
                    return null;
                }

                $pendingCount = (int) ($event->pending_proposals_count ?? 0);

                return [
                    'category' => 'organizer_unanswered_proposals',
                    'title' => __('ui.notifications.scheduled.organizer_unanswered_title', ['event' => (string) $event->name]),
                    'lines' => [
                        __('ui.notifications.scheduled.organizer_unanswered_line', ['count' => $pendingCount]),
                    ],
                    'url' => route('events.show', ['event' => $event, 'tab' => 'proposals'], false),
                    'dedupe_key' => $this->dedupeKey('organizer_unanswered_proposals', (int) $event->id, 0, $localNow),
                ];
            })
            ->filter();
    }

    /**
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectInterestedEnrollmentWindows(User $user, CarbonImmutable $referenceNow, CarbonImmutable $localNow, DateTimeZone $timezone): Collection
    {
        $offsets = collect(config('scheduled_notifications.days_before.enrollment_window', [1, 0]))
            ->map(fn ($offset) => (int) $offset)
            ->unique()
            ->values()
            ->all();

        return $user->interestedEvents()
            ->with(['enrollmentWindows' => fn ($query) => $query->orderBy('starts_at')])
            ->get()
            ->map(function (Event $event) use ($referenceNow, $localNow, $timezone, $offsets): ?array {
                /** @var EventEnrollmentWindow|null $nextWindow */
                $nextWindow = $event->enrollmentWindows
                    ->first(fn (EventEnrollmentWindow $window): bool => $window->starts_at !== null && $window->starts_at->greaterThanOrEqualTo($referenceNow));

                if ($nextWindow?->starts_at === null) {
                    return null;
                }

                $localWindowStart = CarbonImmutable::instance($nextWindow->starts_at)->setTimezone($timezone);
                $daysUntilStart = $this->daysUntil($localNow, $localWindowStart);
                if (! in_array($daysUntilStart, $offsets, true)) {
                    return null;
                }

                return [
                    'category' => 'interested_enrollment_window',
                    'title' => __('ui.notifications.scheduled.enrollment_window_title', ['event' => (string) $event->name]),
                    'lines' => [
                        __('ui.notifications.scheduled.enrollment_window_line', [
                            'when' => $localWindowStart->format('Y-m-d H:i'),
                            'days' => $daysUntilStart,
                        ]),
                    ],
                    'url' => route('events.show', ['event' => $event], false),
                    'dedupe_key' => $this->dedupeKey('interested_enrollment_window', (int) $event->id, $daysUntilStart, $localNow),
                ];
            })
            ->filter();
    }

    /**
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectDashboardFeedItems(User $user, CarbonImmutable $referenceNow, CarbonImmutable $localNow, DateTimeZone $timezone): Collection
    {
        $offsets = collect(config('scheduled_notifications.days_before.dashboard_feed', [3]))
            ->map(fn ($offset) => (int) $offset)
            ->unique()
            ->values()
            ->all();

        $rows = $this->upcomingFeedQueryService->dedupeAndSort(
            $this->upcomingFeedQueryService->buildUnifiedUpcomingRows((int) $user->id)
        );

        $dueRows = $rows
            ->map(function (array $row) use ($localNow, $timezone): array {
                $sortAt = CarbonImmutable::parse((string) $row['sort_at'], 'UTC')->setTimezone($timezone);

                return [
                    'kind' => (string) $row['kind'],
                    'id' => (int) $row['id'],
                    'sort_at' => $sortAt,
                    'days_until' => $this->daysUntil($localNow, $sortAt),
                ];
            })
            ->filter(fn (array $row): bool => in_array((int) $row['days_until'], $offsets, true))
            ->values();

        if ($dueRows->isEmpty()) {
            return collect();
        }

        $eventIds = $dueRows->where('kind', 'event')->pluck('id')->all();
        $activityIds = $dueRows->where('kind', 'activity')->pluck('id')->all();

        $events = Event::query()->whereKey($eventIds)->get()->keyBy('id');
        $activities = Activity::query()->whereKey($activityIds)->get()->keyBy('id');

        $lines = $dueRows->map(function (array $row) use ($events, $activities): string {
            if ($row['kind'] === 'event') {
                /** @var Event|null $event */
                $event = $events->get($row['id']);

                return __('ui.notifications.scheduled.dashboard_feed_event_line', [
                    'name' => (string) ($event?->name ?? '—'),
                    'when' => $row['sort_at']->format('Y-m-d H:i'),
                ]);
            }

            /** @var Activity|null $activity */
            $activity = $activities->get($row['id']);

            return __('ui.notifications.scheduled.dashboard_feed_activity_line', [
                'name' => (string) ($activity?->name ?? '—'),
                'when' => $row['sort_at']->format('Y-m-d H:i'),
            ]);
        })->all();

        return collect([[
            'category' => 'dashboard_feed',
            'title' => __('ui.notifications.scheduled.dashboard_feed_title', ['count' => count($lines)]),
            'lines' => $lines,
            'url' => route('dashboard'),
            'dedupe_key' => $this->dedupeKey('dashboard_feed', (int) $user->id, 0, $localNow),
        ]]);
    }

    /**
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectParticipantCancellationDeadlines(User $user, CarbonImmutable $referenceNow, CarbonImmutable $localNow, DateTimeZone $timezone): Collection
    {
        $offsets = collect(config('scheduled_notifications.days_before.participant_cancellation_deadline', [2]))
            ->map(fn ($offset) => (int) $offset)
            ->unique()
            ->values()
            ->all();

        return Activity::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id)->where('is_absent', false))
            ->whereNotNull('cancellation_deadline_in_hours')
            ->whereNull('cancelled_at')
            ->with('slot')
            ->get()
            ->map(function (Activity $activity) use ($localNow, $timezone, $offsets): ?array {
                $activityStart = $activity->slot?->starts_at ?? $activity->starts_at;
                if ($activityStart === null || $activity->cancellation_deadline_in_hours === null) {
                    return null;
                }

                $deadline = CarbonImmutable::instance($activityStart)
                    ->subHours((int) $activity->cancellation_deadline_in_hours)
                    ->setTimezone($timezone);

                $daysUntilDeadline = $this->daysUntil($localNow, $deadline);
                if (! in_array($daysUntilDeadline, $offsets, true)) {
                    return null;
                }

                return [
                    'category' => 'participant_cancellation_deadline',
                    'title' => __('ui.notifications.scheduled.participant_deadline_title', ['activity' => (string) $activity->name]),
                    'lines' => [
                        __('ui.notifications.scheduled.participant_deadline_line', [
                            'when' => $deadline->format('Y-m-d H:i'),
                        ]),
                    ],
                    'url' => route('activities.show', ['activity' => $activity], false),
                    'dedupe_key' => $this->dedupeKey('participant_cancellation_deadline', (int) $activity->id, $daysUntilDeadline, $localNow),
                ];
            })
            ->filter();
    }

    /**
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectHostLowParticipationWarnings(User $user, CarbonImmutable $referenceNow, CarbonImmutable $localNow, DateTimeZone $timezone): Collection
    {
        $offsets = collect(config('scheduled_notifications.days_before.host_low_participation', [1]))
            ->map(fn ($offset) => (int) $offset)
            ->unique()
            ->values()
            ->all();

        return Activity::query()
            ->where('created_by', $user->id)
            ->whereNotNull('min_participants')
            ->whereNotNull('cancellation_deadline_in_hours')
            ->whereNull('cancelled_at')
            ->with('slot')
            ->withCount(['participants as active_participants_count' => fn ($query) => $query->where('is_absent', false)])
            ->get()
            ->map(function (Activity $activity) use ($localNow, $timezone, $offsets): ?array {
                $minimum = (int) ($activity->min_participants ?? 0);
                $current = (int) ($activity->active_participants_count ?? 0);
                if ($minimum <= 0 || $current >= $minimum) {
                    return null;
                }

                $activityStart = $activity->slot?->starts_at ?? $activity->starts_at;
                if ($activityStart === null || $activity->cancellation_deadline_in_hours === null) {
                    return null;
                }

                $deadline = CarbonImmutable::instance($activityStart)
                    ->subHours((int) $activity->cancellation_deadline_in_hours)
                    ->setTimezone($timezone);

                $daysUntilDeadline = $this->daysUntil($localNow, $deadline);
                if (! in_array($daysUntilDeadline, $offsets, true)) {
                    return null;
                }

                return [
                    'category' => 'host_low_participation',
                    'title' => __('ui.notifications.scheduled.host_low_participation_title', ['activity' => (string) $activity->name]),
                    'lines' => [
                        __('ui.notifications.scheduled.host_low_participation_line', [
                            'current' => $current,
                            'minimum' => $minimum,
                            'when' => $deadline->format('Y-m-d H:i'),
                        ]),
                    ],
                    'url' => route('activities.show', ['activity' => $activity], false),
                    'dedupe_key' => $this->dedupeKey('host_low_participation', (int) $activity->id, $daysUntilDeadline, $localNow),
                ];
            })
            ->filter();
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

    private function daysUntil(CarbonImmutable $localNow, CarbonImmutable $targetDateTime): int
    {
        return $localNow->startOfDay()->diffInDays($targetDateTime->startOfDay(), false);
    }

    private function dedupeKey(string $type, int $entityId, int $offset, CarbonImmutable $localNow): string
    {
        return sprintf('%s:%d:%d:%s', $type, $entityId, $offset, $localNow->toDateString());
    }
}
