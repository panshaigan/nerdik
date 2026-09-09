<?php

namespace App\Services;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityUser;
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
    private const STATS_VERSION = 'v2';

    private const INTERESTED_COUNT_VERSION = 'v1';

    private const PENDING_PROPOSALS_VERSION = 'v1';

    private const TTL_SECONDS = 120;

    /**
     * Cached confirmed programme counts for the shell stats row (invalidated via observers + TTL).
     *
     * @return array{0: int, 1: int, 2: int|null} [confirmedActivitiesCount, confirmedParticipantsCount, availablePlaces]
     *                                            availablePlaces is null when any programme activity is uncapped (display as ∞).
     */
    public function programmeStats(int $eventId): array
    {
        $key = $this->statsKey($eventId);

        /** @var array{confirmedActivities: int, confirmedParticipants: int, availablePlaces: int|null}|null $cached */
        $cached = Cache::get($key);
        if (
            is_array($cached)
            && isset($cached['confirmedActivities'], $cached['confirmedParticipants'])
            && array_key_exists('availablePlaces', $cached)
        ) {
            $availablePlaces = $cached['availablePlaces'];

            return [
                (int) $cached['confirmedActivities'],
                (int) $cached['confirmedParticipants'],
                $availablePlaces === null ? null : (int) $availablePlaces,
            ];
        }

        [$activities, $participants, $availablePlaces] = $this->computeProgrammeStats($eventId);

        Cache::put($key, [
            'confirmedActivities' => $activities,
            'confirmedParticipants' => $participants,
            'availablePlaces' => $availablePlaces,
        ], now()->addSeconds(self::TTL_SECONDS));

        return [$activities, $participants, $availablePlaces];
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
     * @return array{0: int, 1: int, 2: int|null}
     */
    private function computeProgrammeStats(int $eventId): array
    {
        $activitiesBase = Activity::query()
            ->whereHas('slot', fn ($q) => $q->where('event_id', $eventId))
            ->whereNull('cancelled_at');

        $confirmedActivitiesCount = (clone $activitiesBase)->count();

        $confirmedParticipantsCount = (int) ActivityUser::query()
            ->whereNull('activity_user.deleted_at')
            ->whereIn('activity_id', $activitiesBase->clone()->select('activities.id'))
            ->count();

        $availablePlaces = $this->computeAvailablePlaces($activitiesBase->clone());

        return [$confirmedActivitiesCount, $confirmedParticipantsCount, $availablePlaces];
    }

    /**
     * Sum of max_participants on programme activities; null if any activity is uncapped.
     *
     * @param  Builder<Activity>  $activitiesBase
     */
    private function computeAvailablePlaces(Builder $activitiesBase): ?int
    {
        if ((clone $activitiesBase)->whereNull('max_participants')->exists()) {
            return null;
        }

        return (int) (clone $activitiesBase)->sum('max_participants');
    }
}
