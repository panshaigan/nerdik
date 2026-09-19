<?php

namespace App\Services;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL-backed read-through cache for expensive aggregates on the event show page.
 *
 * Uses Laravel's default cache store ({@see config/cache.php}, typically `database` → `cache` table).
 *
 * Performance: {@see self::programmeStats()}, {@see self::eventInterestedCount()}, and {@see self::hasPendingProposals()}
 * avoid repeat queries on shell renders when entries exist (invalidated via observers + TTL).
 * The plan tab still performs a full slot eager-load on first mount when the user switches to “plan”; this cache does not replace that cost.
 */
class EventShowReadCache
{
    private const STATS_VERSION = 'v6';

    private const INTERESTED_COUNT_VERSION = 'v1';

    private const PENDING_PROPOSALS_VERSION = 'v1';

    private const TTL_SECONDS = 120;

    public function __construct(private ActivityPeopleStats $activityPeopleStats) {}

    /**
     * Cached confirmed programme counts for the shell stats row (invalidated via observers + TTL).
     *
     * @return array{0: int, 1: int, 2: int|null} [confirmedActivitiesCount, confirmedParticipantsCount, availablePlaces]
     *                                            confirmedParticipantsCount includes each distinct host not already signed up.
     *                                            availablePlaces includes one seat per distinct non-passive host.
     *                                            availablePlaces is null when any programme activity is uncapped (display as ∞).
     */
    public function programmeStats(int $eventId): array
    {
        $payload = $this->resolvedProgrammeStats($eventId);
        $availablePlaces = $payload['availablePlaces'];

        return [
            $payload['confirmedActivities'],
            $payload['confirmedParticipants'],
            $availablePlaces,
        ];
    }

    /**
     * Signup seats only (hosts excluded). Used for remaining-places notifications.
     */
    public function programmeSignupCount(int $eventId): int
    {
        return $this->resolvedProgrammeStats($eventId)['confirmedSignups'];
    }

    /**
     * Participant caps only (host seats excluded). Used for remaining-places notifications.
     */
    public function programmeSignupCapacity(int $eventId): ?int
    {
        return $this->resolvedProgrammeStats($eventId)['availableSignupPlaces'];
    }

    public function forgetProgrammeStats(int $eventId): void
    {
        Cache::forget($this->statsKey($eventId));
    }

    /**
     * Cached count of users interested in the event (invalidated when interests change + TTL).
     */
    public function eventInterestedCount(int $eventId): int
    {
        $key = $this->interestedCountKey($eventId);

        /** @var array{count: int}|null $cached */
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['count'])) {
            return (int) $cached['count'];
        }

        $interestType = Relation::getMorphAlias(Event::class) ?? Event::class;

        $count = (int) DB::table('user_interests')
            ->where('interest_type', $interestType)
            ->where('interest_id', $eventId)
            ->count();

        Cache::put($key, ['count' => $count], now()->addSeconds(self::TTL_SECONDS));

        return $count;
    }

    /**
     * Prefer calling this when event interested-users pivot changes.
     */
    public function forgetEventInterestedCount(int $eventId): void
    {
        Cache::forget($this->interestedCountKey($eventId));
    }

    /**
     * Whether the event has at least one pending activity proposal (organizer shell tab visibility).
     * Invalidated when proposals change + TTL.
     */
    public function hasPendingProposals(int $eventId): bool
    {
        $key = $this->pendingProposalsKey($eventId);

        /** @var array{value: bool}|null $cached */
        $cached = Cache::get($key);
        if (is_array($cached) && array_key_exists('value', $cached)) {
            return (bool) $cached['value'];
        }

        $has = ActivityProposal::query()
            ->where('event_id', $eventId)
            ->where('status', ActivityProposalStatus::Pending)
            ->exists();

        Cache::put($key, ['value' => $has], now()->addSeconds(self::TTL_SECONDS));

        return $has;
    }

    public function forgetPendingProposalsFlag(int $eventId): void
    {
        Cache::forget($this->pendingProposalsKey($eventId));
    }

    private function statsKey(int $eventId): string
    {
        return 'event_show.programme_stats.'.self::STATS_VERSION.'.'.$eventId;
    }

    private function interestedCountKey(int $eventId): string
    {
        return 'event_show.interested_count.'.self::INTERESTED_COUNT_VERSION.'.'.$eventId;
    }

    private function pendingProposalsKey(int $eventId): string
    {
        return 'event_show.pending_proposals.'.self::PENDING_PROPOSALS_VERSION.'.'.$eventId;
    }

    /**
     * @return array{confirmedActivities: int, confirmedParticipants: int, confirmedSignups: int, availablePlaces: int|null, availableSignupPlaces: int|null}
     */
    private function resolvedProgrammeStats(int $eventId): array
    {
        $key = $this->statsKey($eventId);

        /** @var array{confirmedActivities: int, confirmedParticipants: int, confirmedSignups: int, availablePlaces: int|null, availableSignupPlaces: int|null}|null $cached */
        $cached = Cache::get($key);
        if (
            is_array($cached)
            && isset($cached['confirmedActivities'], $cached['confirmedParticipants'], $cached['confirmedSignups'])
            && array_key_exists('availablePlaces', $cached)
            && array_key_exists('availableSignupPlaces', $cached)
        ) {
            $availablePlaces = $cached['availablePlaces'];
            $availableSignupPlaces = $cached['availableSignupPlaces'];

            return [
                'confirmedActivities' => (int) $cached['confirmedActivities'],
                'confirmedParticipants' => (int) $cached['confirmedParticipants'],
                'confirmedSignups' => (int) $cached['confirmedSignups'],
                'availablePlaces' => $availablePlaces === null ? null : (int) $availablePlaces,
                'availableSignupPlaces' => $availableSignupPlaces === null ? null : (int) $availableSignupPlaces,
            ];
        }

        $computed = $this->computeProgrammeStats($eventId);

        Cache::put($key, $computed, now()->addSeconds(self::TTL_SECONDS));

        return $computed;
    }

    /**
     * @return array{confirmedActivities: int, confirmedParticipants: int, confirmedSignups: int, availablePlaces: int|null, availableSignupPlaces: int|null}
     */
    private function computeProgrammeStats(int $eventId): array
    {
        $activitiesBase = Activity::query()
            ->whereHas('slot', fn ($q) => $q->where('event_id', $eventId))
            ->whereNull('cancelled_at');

        $activityIds = $activitiesBase->clone()->select('activities.id');

        $availableSignupPlaces = $this->computeSignupCapacity($activitiesBase->clone());
        $availablePlaces = $this->computeAvailablePlaces($activitiesBase->clone(), $availableSignupPlaces);

        return [
            'confirmedActivities' => (clone $activitiesBase)->count(),
            'confirmedParticipants' => $this->activityPeopleStats->totalIncludingHosts($activityIds),
            'confirmedSignups' => $this->activityPeopleStats->signupCount($activityIds),
            'availablePlaces' => $availablePlaces,
            'availableSignupPlaces' => $availableSignupPlaces,
        ];
    }

    /**
     * Sum of max_participants on programme activities; null if any activity is uncapped.
     *
     * @param  Builder<Activity>  $activitiesBase
     */
    private function computeSignupCapacity(Builder $activitiesBase): ?int
    {
        if ((clone $activitiesBase)->whereNull('max_participants')->exists()) {
            return null;
        }

        return (int) (clone $activitiesBase)->sum('max_participants');
    }

    /**
     * Participant caps plus one seat per distinct non-passive host.
     * A host who runs several activities is counted once. Null if any activity is uncapped.
     *
     * @param  Builder<Activity>  $activitiesBase
     */
    private function computeAvailablePlaces(Builder $activitiesBase, ?int $signupCapacity): ?int
    {
        if ($signupCapacity === null) {
            return null;
        }

        $hostSeats = (int) (clone $activitiesBase)
            ->where('is_host_passive', false)
            ->whereNotNull('created_by')
            ->distinct()
            ->count('created_by');

        return $signupCapacity + $hostSeats;
    }
}
