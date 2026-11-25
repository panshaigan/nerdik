<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\Activity;

trait WithParticipantsInfoJoinModal
{
    public bool $participantsInfoModalOpen = false;

    protected function openParticipantsInfoModalAfterJoin(Activity $activity): void
    {
        if (! filled(rich_text_excerpt($activity->participants_only_description))) {
            return;
        }

        $this->participantsInfoModalOpen = true;
        $this->switchToParticipantsInfoContextTab();
    }

    protected function switchToParticipantsInfoContextTab(): void
    {
        if (property_exists($this, 'tab')) {
            $this->tab = 'info';
        }

        if (property_exists($this, 'activityPreviewTab')) {
            $this->activityPreviewTab = 'info';
        }
    }
}
