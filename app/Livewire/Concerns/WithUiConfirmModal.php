<?php

namespace App\Livewire\Concerns;

trait WithUiConfirmModal
{
    public bool $confirmModalOpen = false;

    public string $confirmModalTitle = '';

    public string $confirmModalMessage = '';

    public ?string $pendingAction = null;

    public ?int $pendingParticipantId = null;

    protected function openConfirm(string $action, string $title, string $message, ?int $participantId = null): void
    {
        $this->pendingAction = $action;
        $this->pendingParticipantId = $participantId;
        $this->confirmModalTitle = $title;
        $this->confirmModalMessage = $message;
        $this->confirmModalOpen = true;
    }

    public function closeConfirm(): void
    {
        $this->confirmModalOpen = false;
        $this->confirmModalTitle = '';
        $this->confirmModalMessage = '';
        $this->pendingAction = null;
        $this->pendingParticipantId = null;
    }
}
