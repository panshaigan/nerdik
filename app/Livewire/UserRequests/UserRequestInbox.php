<?php

declare(strict_types=1);

namespace App\Livewire\UserRequests;

use App\Models\UserRequest;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class UserRequestInbox extends Component
{
    use Toast;

    #[Url(as: 'request')]
    public ?int $openRequestId = null;

    public function mount(): void
    {
        if ($this->openRequestId === null) {
            return;
        }

        $exists = UserRequest::query()->whereKey($this->openRequestId)->exists();
        if (! $exists) {
            $this->openRequestId = null;
            $this->error(__('ui.user_requests.invalid_request'));

            return;
        }

        $this->openRequestModal((string) $this->openRequestId);
    }

    #[On('user-requests-updated')]
    public function refreshInbox(): void
    {
        //
    }

    #[On('user-request-modal-closed')]
    public function clearOpenRequestFromUrl(): void
    {
        $this->openRequestId = null;
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
