<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use App\Models\UserHostBan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserHostBanService
{
    public function __construct(
        private readonly ActivityParticipantRosterService $roster,
    ) {}

    public function viewerCanBan(User $viewer): bool
    {
        return Activity::query()
            ->where('created_by', $viewer->id)
            ->exists();
    }

    public function isBannedByHost(User $banned, User $host): bool
    {
        return UserHostBan::query()
            ->where('host_user_id', $host->id)
            ->where('banned_user_id', $banned->id)
            ->exists();
    }

    public function isBannedFromActivity(User $user, Activity $activity): bool
    {
        $hostId = $activity->created_by;
        if ($hostId === null) {
            return false;
        }

        return UserHostBan::query()
            ->where('host_user_id', $hostId)
            ->where('banned_user_id', $user->id)
            ->exists();
    }

    public function ban(User $host, User $target): void
    {
        if ((int) $host->id === (int) $target->id) {
            throw ValidationException::withMessages([
                '_' => [__('ui.profile.cannot_ban_self')],
            ]);
        }

        if (! $this->viewerCanBan($host)) {
            abort(403, __('ui.profile.ban_unauthorized'));
        }

        if ($this->isBannedByHost($target, $host)) {
            return;
        }

        DB::transaction(function () use ($host, $target): void {
            UserHostBan::query()->create([
                'host_user_id' => $host->id,
                'banned_user_id' => $target->id,
            ]);

            $this->removeFromUpcomingHostedActivities($host, $target);
        });
    }

    public function unban(User $host, User $target): void
    {
        if (! $this->viewerCanBan($host)) {
            abort(403, __('ui.profile.ban_unauthorized'));
        }

        UserHostBan::query()
            ->where('host_user_id', $host->id)
            ->where('banned_user_id', $target->id)
            ->delete();
    }

    private function removeFromUpcomingHostedActivities(User $host, User $target): void
    {
        $now = now('UTC');

        $upcomingActivityIds = Activity::query()
            ->with('slot')
            ->where('created_by', $host->id)
            ->whereNull('cancelled_at')
            ->get()
            ->filter(function (Activity $activity) use ($now): bool {
                $endsAt = $activity->occurrenceEndsAt();

                return $endsAt !== null && $endsAt->clone()->utc()->gte($now);
            })
            ->pluck('id')
            ->all();

        if ($upcomingActivityIds === []) {
            return;
        }

        $participants = ActivityUser::query()
            ->whereIn('activity_id', $upcomingActivityIds)
            ->where('user_id', $target->id)
            ->get();

        foreach ($participants as $participant) {
            $this->roster->removeParticipant($participant);
        }

        ActivityWaitlistEntry::query()
            ->whereIn('activity_id', $upcomingActivityIds)
            ->where('user_id', $target->id)
            ->delete();
    }
}
