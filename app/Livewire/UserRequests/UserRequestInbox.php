<?php

declare(strict_types=1);

namespace App\Livewire\UserRequests;

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class UserRequestInbox extends Component
{
    #[Url(as: 'request')]
    public ?int $openRequestId = null;

    public function mount(): void
    {
        if ($this->openRequestId !== null) {
            $this->openRequestModal((string) $this->openRequestId);
        }
    }

    #[On('user-requests-updated')]
    public function refreshInbox(): void
    {
        //
    }

    public function openRequestModal(string $requestId): void
    {
        $this->dispatch('open-user-request-modal', requestId: (int) $requestId);
    }

    public function render()
    {
        return view('livewire.user-requests.user-request-inbox');
    }
}
