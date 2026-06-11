<?php

namespace App\Services\Notifications\Scheduled;

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
        return collect()
            ->concat($this->collectInterestedEnrollmentWindows($user, $referenceNow))
            ->concat($this->collectDashboardFeedItems($user, $referenceNow))
            ->concat($this->collectParticipantCancellationDeadlines($user, $referenceNow))
            ->concat($this->collectHostLowParticipationWarnings($user, $referenceNow))
            ->values()
            ->all();
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

        $dueRows = $rows
            ->map(function (array $row): array {
                $sortAt = CarbonImmutable::parse((string) $row['sort_at'], 'UTC');

                return [
                    'kind' => (string) $row['kind'],
                    'id' => (int) $row['id'],
                    'sort_at' => $sortAt,
                ];
            })
            ->filter(fn (array $row): bool => $this->isWithinLookahead($referenceNow, $row['sort_at']))
            ->values();

        if ($dueRows->isEmpty()) {
            return collect();
        }

        $eventIds = $dueRows->where('kind', 'event')->pluck('id')->all();
        $activityIds = $dueRows->where('kind', 'activity')->pluck('id')->all();

        $events = Event::query()->whereKey($eventIds)->get()->keyBy('id');
        $activities = Activity::query()->whereKey($activityIds)->get()->keyBy('id');
        $timezone = $this->timezoneForUser($user);

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

        return collect([[
            'category' => 'dashboard_feed',
            'title' => __('ui.notifications.scheduled.dashboard_feed_title', ['count' => count($lines)]),
            'lines' => $lines,
            'url' => route('dashboard'),
            'dedupe_key' => $this->dedupeKey('dashboard_feed', (int) $user->id, $referenceNow->startOfHour()),
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
                if ($deadline === null || ! $this->isWithinLookahead($referenceNow, $deadline)) {
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
     * @return Collection<int, array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function collectHostLowParticipationWarnings(User $user, CarbonImmutable $referenceNow): Collection
    {
        return Activity::query()
            ->where('created_by', $user->id)
            ->whereNotNull('min_participants')
            ->whereNotNull('cancellation_deadline_in_hours')
            ->whereNull('cancelled_at')
            ->with('slot')
            ->withCount(['participants as active_participants_count' => fn ($query) => $query->where('is_absent', false)])
            ->get()
            ->map(function (Activity $activity) use ($user, $referenceNow): ?array {
                $minimum = (int) ($activity->min_participants ?? 0);
                $current = (int) ($activity->active_participants_count ?? 0);
                if ($minimum <= 0 || $current >= $minimum) {
                    return null;
                }

                $deadline = $this->cancellationDeadlineAt($activity);
                if ($deadline === null || ! $this->isWithinLookahead($referenceNow, $deadline)) {
                    return null;
                }

                return [
                    'category' => 'host_low_participation',
                    'title' => __('ui.notifications.scheduled.host_low_participation_title', ['activity' => (string) $activity->name]),
                    'lines' => [
                        __('ui.notifications.scheduled.host_low_participation_line', [
                            'current' => $current,
                            'minimum' => $minimum,
                            'when' => $deadline->setTimezone($this->timezoneForUser($user))->format('Y-m-d H:i'),
                        ]),
                    ],
                    'url' => route('activities.show', ['activity' => $activity], false),
                    'dedupe_key' => $this->dedupeKey('host_low_participation', (int) $activity->id, $deadline),
                ];
            })
            ->filter();
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
