<?php

declare(strict_types=1);

namespace App\Livewire\UserRequests;

use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use App\Services\UserRequests\UserInviteSearchService;
use App\Services\UserRequests\UserRequestService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;

class InviteUserRequest extends Component
{
    use Toast;

    public string $type;

    public int $subjectId;

    public ?string $dataUi = null;

    public bool $modalOpen = false;

    public ?int $selectedUserId = null;

    public string $message = '';

    /** @var list<array{id: int, name: string, display_name: string, avatar: string}> */
    public array $userOptions = [];

    /** @var array{id: int, name: string, display_name: string, avatar: string}|null */
    public ?array $selectedUserOption = null;

    public string $lastSearchTerm = '';

    public function mount(string $type, int $subjectId, ?string $dataUi = null): void
    {
        $this->type = $type;
        $this->subjectId = $subjectId;
        $this->dataUi = $dataUi;
    }

    public function openModal(): void
    {
        $this->selectedUserId = null;
        $this->selectedUserOption = null;
        $this->message = '';
        $this->userOptions = [];
        $this->lastSearchTerm = '';
        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->selectedUserId = null;
        $this->selectedUserOption = null;
        $this->message = '';
        $this->userOptions = [];
        $this->lastSearchTerm = '';
    }

    public function updatedSelectedUserId(mixed $value): void
    {
        $this->selectedUserId = filled($value) ? (int) $value : null;
    }

    public function updatedLastSearchTerm(UserInviteSearchService $search): void
    {
        $this->search($this->lastSearchTerm, $search);
    }

    public function search(string $value, UserInviteSearchService $search): void
    {
        $this->lastSearchTerm = trim($value);
        $this->userOptions = $search->search(
            UserRequestType::from($this->type),
            $this->subjectId,
            $value,
            Auth::user(),
        );
    }

    public function selectUser(int $userId): void
    {
        $option = collect($this->userOptions)->firstWhere('id', $userId);

        if ($option === null) {
            return;
        }

        $this->selectedUserId = $userId;
        $this->selectedUserOption = $option;
        $this->lastSearchTerm = '';
        $this->userOptions = [];
    }

    public function clearSelectedUser(): void
    {
        $this->selectedUserId = null;
        $this->selectedUserOption = null;
        $this->lastSearchTerm = '';
        $this->userOptions = [];
    }

    public function send(UserRequestService $requests): void
    {
        $requester = Auth::user();
        if ($requester === null) {
            return;
        }

        $recipient = $this->selectedUserId !== null
            ? User::query()->find($this->selectedUserId)
            : null;

        if ($recipient === null) {
            $this->error(__('ui.user_requests.invite_user_select_user'));

            return;
        }

        $requestType = UserRequestType::from($this->type);
        $subject = $this->resolveSubject($requestType);

        if ($subject === null) {
            $this->error(__('ui.user_requests.invalid_subject'));

            return;
        }

        try {
            $requests->send(
                $requestType,
                $requester,
                $recipient,
                $subject,
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

    public function modalTitle(): string
    {
        return match (UserRequestType::from($this->type)) {
            UserRequestType::ActivityInvite => __('ui.user_requests.invite_participant_title'),
            UserRequestType::OrganizationInvite => __('ui.user_requests.invite_organization_title'),
            default => __('ui.user_requests.review_request'),
        };
    }

    public function triggerLabel(): string
    {
        return match (UserRequestType::from($this->type)) {
            UserRequestType::ActivityInvite => __('ui.user_requests.invite_participant'),
            UserRequestType::OrganizationInvite => __('ui.user_requests.invite_to_organization'),
            default => __('ui.user_requests.send_invite'),
        };
    }

    public function triggerTitle(): string
    {
        return match (UserRequestType::from($this->type)) {
            UserRequestType::OrganizationInvite => __('ui.user_requests.invite_organization_action'),
            default => $this->triggerLabel(),
        };
    }

    public function usesIconTrigger(): bool
    {
        return UserRequestType::from($this->type) === UserRequestType::OrganizationInvite;
    }

    public function render()
    {
        return view('livewire.user-requests.invite-user-request');
    }

    private function resolveSubject(UserRequestType $type): Activity|Organization|null
    {
        return match ($type) {
            UserRequestType::ActivityInvite => Activity::query()->find($this->subjectId),
            UserRequestType::OrganizationInvite => Organization::query()->find($this->subjectId),
            default => null,
        };
    }
}
