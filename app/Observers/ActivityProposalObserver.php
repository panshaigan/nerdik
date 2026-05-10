<?php

namespace App\Observers;

use App\Models\ActivityProposal;
use App\Services\EventShowReadCache;

class ActivityProposalObserver
{
    public function saved(ActivityProposal $activityProposal): void
    {
        $cache = app(EventShowReadCache::class);
        $cache->forgetProgrammeStats((int) $activityProposal->event_id);
        $cache->forgetPendingProposalsFlag((int) $activityProposal->event_id);
    }

    public function deleted(ActivityProposal $activityProposal): void
    {
        $cache = app(EventShowReadCache::class);
        $cache->forgetProgrammeStats((int) $activityProposal->event_id);
        $cache->forgetPendingProposalsFlag((int) $activityProposal->event_id);
    }
}
