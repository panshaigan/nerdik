<?php

namespace App\Services;

use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Slot;
use Illuminate\Support\Facades\Cache;

/**
 * PostgreSQL-backed read-through cache for expensive aggregates on the event show page.
 *
 * Uses Laravel's default cache store ({@see config/cache.php}, typically `database` → `cache` table).
 *
 * Performance: {@see self::programmeStats()} avoids recounting confirmed programme metrics on repeat {@see ShowEvent}
 * renders when the fingerprint is unchanged. The plan tab still performs a full slot eager-load on first mount when the user switches
 * to “plan”; this cache does not replace that cost.
 */
class EventShowReadCache
{
    private const STATS_VERSION = 'v1';

    private const TTL_SECONDS = 120;

    /**
     * Cached confirmed programme counts for the shell stats row (invalidated via observers + TTL/fingerprint).
     *
     * @return array{0: int, 1: int} [confirmedActivitiesCount, confirmedParticipantsCount]
     */
    public function programmeStats(int $eventId): array
    {
        $key = $this->statsKey($eventId);
        $fingerprint = $this->programmeFingerprint($eventId);

        /** @var array{fingerprint: string, confirmedActivities: int, confirmedParticipants: int}|null $cached */
        $cached = Cache::get($key);
        if (
            is_array($cached)
            && isset($cached['fingerprint'], $cached['confirmedActivities'], $cached['confirmedParticipants'])
            && $cached['fingerprint'] === $fingerprint
        ) {
            return [(int) $cached['confirmedActivities'], (int) $cached['confirmedParticipants']];
        }

        [$activities, $participants] = $this->computeProgrammeStats($eventId);

        Cache::put($key, [
            'fingerprint' => $fingerprint,
            'confirmedActivities' => $activities,
            'confirmedParticipants' => $participants,
        ], now()->addSeconds(self::TTL_SECONDS));

        return [$activities, $participants];
    }

    public function forgetProgrammeStats(int $eventId): void
    {
        Cache::forget($this->statsKey($eventId));
    }

    /**
     * Cheap invalidation signal when programme-linked rows change.
     */
    public function programmeFingerprint(int $eventId): string
    {
        $slotUpdatedAt = Slot::query()->where('event_id', $eventId)->max('updated_at');
        $activityUpdatedAt = Activity::query()
            ->whereHas('slot', fn ($q) => $q->where('event_id', $eventId))
            ->max('updated_at');

        $programmeActivityIds = Slot::query()
            ->where('event_id', $eventId)
            ->whereNotNull('activity_id')
            ->pluck('activity_id');

        $participantUpdatedAt = $programmeActivityIds->isNotEmpty()
            ? ActivityUser::query()
                ->whereNull('activity_user.deleted_at')
                ->whereIn('activity_id', $programmeActivityIds->all())
                ->max('updated_at')
            : null;

        return hash('sha256', implode('|', [
            (string) $slotUpdatedAt,
            (string) $activityUpdatedAt,
            (string) $participantUpdatedAt,
            (string) $programmeActivityIds->count(),
        ]));
    }

    private function statsKey(int $eventId): string
    {
        return 'event_show.programme_stats.'.self::STATS_VERSION.'.'.$eventId;
    }

    /**
     * @return array{0: int, 1: int}
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

        return [$confirmedActivitiesCount, $confirmedParticipantsCount];
    }
}
