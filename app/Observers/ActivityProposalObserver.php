<?php

namespace App\Observers;

use App\Models\ActivityProposal;
use App\Services\EventShowReadCache;

class ActivityProposalObserver
{
    public function saved(ActivityProposal $activityProposal): void
    {
        app(EventShowReadCache::class)->forgetProgrammeStats((int) $activityProposal->event_id);
    }

    public function deleted(ActivityProposal $activityProposal): void
    {
        app(EventShowReadCache::class)->forgetProgrammeStats((int) $activityProposal->event_id);
    }
}
