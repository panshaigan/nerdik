<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\User;

class ContactVisibilityService
{
    public function canViewContactInfo(User $viewer, User $target, Activity $activity): bool
    {
        if ((int) $viewer->id === (int) $target->id) {
            return true;
        }

        $hostId = (int) ($activity->created_by ?? 0);
        if ($hostId === 0) {
            return false;
        }

        $viewerIsHost = (int) $viewer->id === $hostId;
        $targetIsHost = (int) $target->id === $hostId;

        if ($viewerIsHost) {
            return $this->isActiveParticipant($activity->id, $target->id);
        }

        if ($targetIsHost) {
            return $this->isActiveParticipant($activity->id, $viewer->id);
        }

        return false;
    }

    private function isActiveParticipant(int $activityId, int $userId): bool
    {
        return ActivityUser::query()
            ->where('activity_id', $activityId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->exists();
    }
}
