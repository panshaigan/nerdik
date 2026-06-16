<?php

namespace App\Services;

use App\Models\ActivityProposal;
use App\Models\ActivityUser;
use App\Models\User;

class ContactVisibilityService
{
    public function canViewContactInfo(User $viewer, User $target): bool
    {
        if ((int) $viewer->id === (int) $target->id) {
            return true;
        }

        return $this->viewerParticipatesInTargetsActivity($viewer, $target)
            || $this->targetParticipatesInViewersActivity($viewer, $target)
            || $this->targetProposedToViewersEvent($viewer, $target)
            || $this->viewerProposedToTargetsEvent($viewer, $target);
    }

    private function viewerParticipatesInTargetsActivity(User $viewer, User $target): bool
    {
        return $this->userParticipatesInHostsActivity($viewer->id, $target->id);
    }

    private function targetParticipatesInViewersActivity(User $viewer, User $target): bool
    {
        return $this->userParticipatesInHostsActivity($target->id, $viewer->id);
    }

    private function userParticipatesInHostsActivity(int $participantId, int $hostId): bool
    {
        return ActivityUser::query()
            ->where('user_id', $participantId)
            ->whereNull('deleted_at')
            ->whereHas('activity', fn ($query) => $query
                ->where('created_by', $hostId)
                ->whereNull('deleted_at'))
            ->exists();
    }

    private function targetProposedToViewersEvent(User $viewer, User $target): bool
    {
        return $this->userProposedToHostsEvent($target->id, $viewer->id);
    }

    private function viewerProposedToTargetsEvent(User $viewer, User $target): bool
    {
        return $this->userProposedToHostsEvent($viewer->id, $target->id);
    }

    private function userProposedToHostsEvent(int $proposerId, int $eventHostId): bool
    {
        return ActivityProposal::query()
            ->where('created_by', $proposerId)
            ->whereNull('deleted_at')
            ->whereHas('event', fn ($query) => $query
                ->where('created_by', $eventHostId)
                ->whereNull('deleted_at'))
            ->exists();
    }
}
