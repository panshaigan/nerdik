<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Enums\UserRequestType;
use App\Models\UserRequest;
use App\Services\UserRequests\UserRequestDecisionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class RespondToUserRequest extends Component
{
    use Toast;

    public bool $open = false;

    public ?int $requestId = null;

    public string $declineNote = '';

    public ?string $errorMessage = null;

    #[On('open-user-request-modal')]
    public function openModal(int $requestId): void
    {
        $this->requestId = $requestId;
        $this->declineNote = '';
        $this->errorMessage = null;
        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->requestId = null;
        $this->declineNote = '';
        $this->errorMessage = null;
    }

    public function accept(UserRequestDecisionService $decisions): void
    {
        $request = $this->authorizedRequest();
        if ($request === null) {
            return;
        }

        try {
            $decisions->accept($request, Auth::user());
        } catch (ValidationException $e) {
            $this->errorMessage = (string) collect($e->errors())->flatten()->first();

            return;
        }

        $this->success(__('ui.user_requests.accepted'));
        $this->closeModal();
        $this->dispatch('database-notifications-updated', resetPagination: false);
        $this->dispatch('user-requests-updated');
    }

    public function decline(UserRequestDecisionService $decisions): void
    {
        $request = $this->authorizedRequest();
        if ($request === null) {
            return;
        }

        $note = trim($this->declineNote);
        $decisions->decline($request, Auth::user(), $note !== '' ? $note : null);

        $this->success(__('ui.user_requests.declined'));
        $this->closeModal();
        $this->dispatch('database-notifications-updated', resetPagination: false);
        $this->dispatch('user-requests-updated');
    }

    public function render()
    {
        $request = null;
        $canRespond = false;

        if ($this->requestId !== null) {
            $request = UserRequest::query()
                ->with(['requester', 'recipient', 'subject'])
                ->find($this->requestId);

            if ($request !== null) {
                $user = Auth::user();
                $canRespond = $request->isPending()
                    && ! $request->isExpiredByTime()
                    && (
                        (int) $request->recipient_id === (int) $user?->id
                        || ($request->type === UserRequestType::EventOrganizerFlag && $user?->is_admin === true)
                    );
            }
        }

        return view('livewire.notifications.respond-to-user-request', [
            'request' => $request,
            'canRespond' => $canRespond,
        ]);
    }

    private function authorizedRequest(): ?UserRequest
    {
        if ($this->requestId === null) {
            return null;
        }

        $request = UserRequest::query()->find($this->requestId);
        $user = Auth::user();

        if ($request === null || $user === null || ! $request->isPending()) {
            $this->error(__('ui.user_requests.invalid_request'));
            $this->closeModal();

            return null;
        }

        return $request;
    }
}
