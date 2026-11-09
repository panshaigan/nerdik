<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Group;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

class GroupCalendarActivityPrefillService
{
    private const TOKEN_TTL_SECONDS = 86_400;

    public function __construct(
        private readonly GroupAvailabilityService $availability,
        private readonly UserHostBanService $hostBans,
        private readonly ActivityFamiliarityService $familiarity,
    ) {}

    public function makeToken(Group $group, string $date, User $user): string
    {
        return Crypt::encrypt([
            'group_id' => (int) $group->id,
            'date' => $date,
            'user_id' => (int) $user->id,
            'exp' => now()->addSeconds(self::TOKEN_TTL_SECONDS)->getTimestamp(),
        ]);
    }

    public function resolve(?string $token, User $user): ?GroupCalendarActivityPrefillData
    {
        if ($token === null || trim($token) === '') {
            return null;
        }

        try {
            $payload = Crypt::decrypt($token);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $groupId = (int) ($payload['group_id'] ?? 0);
        $date = $payload['date'] ?? null;
        $tokenUserId = (int) ($payload['user_id'] ?? 0);
        $exp = (int) ($payload['exp'] ?? 0);

        if ($groupId <= 0
            || ! is_string($date)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || $tokenUserId !== (int) $user->id
            || $exp < now()->getTimestamp()) {
            return null;
        }

        $dayStart = Carbon::parse($date, display_timezone())->startOfDay();
        $todayStart = Carbon::now(display_timezone())->startOfDay();
        if ($dayStart->lessThan($todayStart)) {
            return null;
        }

        $group = Group::query()->visibleTo($user)->with('tags')->whereKey($groupId)->first();
        if ($group === null) {
            return null;
        }

        $detail = $this->availability->dayDetail($group, Carbon::parse($date));
        $enrollable = $this->enrollableUsers($detail['available'], $user);

        $playerCount = max(1, $enrollable->count());

        $tagIds = $group->tags->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return new GroupCalendarActivityPrefillData(
            group: $group,
            date: $date,
            selfHostedStartsAt: $date.'T18:00',
            tagIds: $tagIds,
            enrollableUsers: $enrollable,
            playerCount: $playerCount,
        );
    }

    public function enrollFromResolved(Activity $activity, GroupCalendarActivityPrefillData $data, User $host): int
    {
        $hostId = (int) $host->id;
        $max = $activity->max_participants;
        $enrolled = 0;

        foreach ($data->enrollableUsers as $member) {
            if ($max !== null && $enrolled >= $max) {
                break;
            }

            $memberId = (int) $member->id;
            if ($memberId === $hostId) {
                continue;
            }

            if ($this->hostBans->isBannedFromActivity($member, $activity)) {
                continue;
            }

            if ($activity->participants()->where('user_id', $memberId)->exists()) {
                continue;
            }

            $activity->participants()->create(
                $this->familiarity->participantCreatePayload($memberId, null)
            );
            $enrolled++;
        }

        if ($enrolled > 0) {
            ActivityParticipationBroadcaster::rosterChanged((int) $activity->id);
        }

        return $enrolled;
    }

    /**
     * @param  Collection<int, User>  $available
     * @return Collection<int, User>
     */
    private function enrollableUsers(Collection $available, User $host): Collection
    {
        return $available
            ->filter(fn (User $member): bool => (int) $member->id !== (int) $host->id)
            ->values();
    }
}
