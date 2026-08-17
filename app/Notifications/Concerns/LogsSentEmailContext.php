<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Enums\SentEmailKind;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\UserRequest;
use Illuminate\Database\Eloquent\Model;

trait LogsSentEmailContext
{
    public function sentEmailKind(): SentEmailKind
    {
        return SentEmailKind::fromPreferenceKey($this->notificationPreferenceKey());
    }

    public function sentEmailRelated(): ?Model
    {
        if (isset($this->activity) && $this->activity instanceof Activity) {
            return $this->activity;
        }

        if (isset($this->event) && $this->event instanceof Event) {
            return $this->event;
        }

        if (isset($this->proposal) && $this->proposal instanceof ActivityProposal) {
            return $this->proposal;
        }

        if (isset($this->request) && $this->request instanceof UserRequest) {
            return $this->request;
        }

        if (isset($this->eventId) && is_int($this->eventId)) {
            return Event::query()->find($this->eventId);
        }

        return null;
    }
}
