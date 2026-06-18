<?php

declare(strict_types=1);

namespace App\Livewire\UserRequests;

use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use App\Services\UserRequests\UserRequestHandlerRegistry;
use App\Services\UserRequests\UserRequestService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;

class SendUserRequest extends Component
{
    use Toast;

    public string $type;

    public ?string $subjectType = null;

    public ?int $subjectId = null;

    public ?int $recipientId = null;

    public bool $modalOpen = false;

    public string $message = '';

    public function mount(
        string $type,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?int $recipientId = null,
    ): void {
        $this->type = $type;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->recipientId = $recipientId;
    }

    public function openModal(): void
    {
        if (! $this->sendable()) {
            return;
        }

        $this->message = '';
        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->message = '';
    }

    public function send(UserRequestService $requests): void
    {
        $requester = Auth::user();
        if ($requester === null) {
            return;
        }

        try {
            $requests->send(
                UserRequestType::from($this->type),
                $requester,
                $this->resolveRecipient(),
                $this->resolveSubject(),
                $this->message !== '' ? $this->message : null,
            );
        } catch (ValidationException $e) {
            $this->error((string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->success(__('ui.user_requests.sent'));
        $this->closeModal();
        $this->dispatch('user-request-sent');
        $this->dispatch('user-requests-updated');
    }

    public function canSend(UserRequestHandlerRegistry $handlers): bool
    {
        return $this->sendable($handlers);
    }

    private function sendable(?UserRequestHandlerRegistry $handlers = null): bool
    {
        $handlers ??= app(UserRequestHandlerRegistry::class);
        $requester = Auth::user();
        if ($requester === null) {
            return false;
        }

        try {
            $handlers->get(UserRequestType::from($this->type))
                ->assertCanSend($requester, $this->resolveRecipient(), $this->resolveSubject());

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function buttonLabel(): string
    {
        return match (UserRequestType::from($this->type)) {
            UserRequestType::OrganizationInvite => __('ui.user_requests.invite_to_organization'),
            UserRequestType::OrganizationJoinRequest => __('ui.user_requests.request_to_join_organization'),
            UserRequestType::ActivityInvite => __('ui.user_requests.invite_to_activity'),
            UserRequestType::EventOrganizerFlag => __('ui.user_requests.request_organizer_access'),
        };
    }

    public function render(UserRequestHandlerRegistry $handlers)
    {
        return view('livewire.user-requests.send-user-request', [
            'sendable' => $this->sendable($handlers),
        ]);
    }

    private function resolveRecipient(): ?User
    {
        if ($this->recipientId === null) {
            return null;
        }

        return User::query()->find($this->recipientId);
    }

    private function resolveSubject(): Organization|Activity|null
    {
        if ($this->subjectType === null || $this->subjectId === null) {
            return null;
        }

        return match ($this->subjectType) {
            'organization' => Organization::query()->find($this->subjectId),
            'activity' => Activity::query()->find($this->subjectId),
            default => null,
        };
    }
}
