<?php

namespace App\Observers;

use App\Models\Slot;
use App\Services\EventShowReadCache;

class SlotObserver
{
    public function saved(Slot $slot): void
    {
        app(EventShowReadCache::class)->forgetProgrammeStats((int) $slot->event_id);
    }

    public function deleted(Slot $slot): void
    {
        app(EventShowReadCache::class)->forgetProgrammeStats((int) $slot->event_id);
    }
}
