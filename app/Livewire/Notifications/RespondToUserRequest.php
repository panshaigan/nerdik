<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Livewire\Concerns\WithFamiliarityPrompt;
use App\Models\Activity;
use App\Models\UserRequest;
use App\Services\ActivityFamiliarityService;
use App\Services\ActivityParticipationService;
use App\Services\UserRequests\UserRequestDecisionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class RespondToUserRequest extends Component
{
    use Toast;
    use WithFamiliarityPrompt;

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
        $this->dispatch('user-request-modal-closed');
    }

    public function closeFamiliarityPrompt(): void
    {
        $reopenRequestId = $this->familiarityPendingAction === 'accept_invite'
            ? $this->familiarityInviteRequestId
            : null;

        $this->familiarityModalOpen = false;
        $this->familiarityActivityId = null;
        $this->familiarityPendingAction = '';
        $this->familiarityInviteRequestId = null;
        $this->familiarityAnswers = [];
        $this->familiaritySubjects = [];

        if ($reopenRequestId !== null) {
            $this->requestId = $reopenRequestId;
            $this->open = true;
        }
    }

    public function accept(UserRequestDecisionService $decisions, ActivityFamiliarityService $familiarity): void
    {
        $request = $this->authorizedRequest();
        if ($request === null) {
            return;
        }

        if ($request->type === UserRequestType::ActivityInvite) {
            $activity = $request->subject;
            if ($activity instanceof Activity
                && $activity->collect_familiarity
                && $familiarity->activityHasFamiliaritySubjects($activity)
            ) {
                $requestId = (int) $request->id;
                $this->open = false;
                $this->beginFamiliarityOrRun(
                    $activity,
                    'accept_invite',
                    function (?array $snapshot) use ($decisions, $familiarity, $requestId): void {
                        $user = Auth::user();
                        if ($user !== null && $snapshot !== null) {
                            $familiarity->stageSnapshot((int) $user->id, $snapshot);
                        }
                        $this->requestId = $requestId;
                        $this->finalizeInviteAccept($decisions);
                    },
                    $requestId,
                );

                return;
            }
        }

        $this->finalizeInviteAccept($decisions);
    }

    /**
     * @param  array<string, mixed>|null  $familiaritySnapshot
     */
    protected function afterFamiliarityCollected(
        string $pendingAction,
        Activity $activity,
        ?array $familiaritySnapshot,
        ActivityParticipationService $participation,
        ?int $inviteRequestId = null,
    ): void {
        if ($pendingAction !== 'accept_invite' || $inviteRequestId === null) {
            return;
        }

        $user = Auth::user();
        if ($user !== null) {
            app(ActivityFamiliarityService::class)->stageSnapshot((int) $user->id, $familiaritySnapshot);
        }

        $this->requestId = $inviteRequestId;
        $this->finalizeInviteAccept(app(UserRequestDecisionService::class));
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
        $modalState = 'not_found';
        $resolvedMessage = null;

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

                if ($canRespond) {
                    $modalState = 'actionable';
                } elseif ($request->isPending() && $request->isExpiredByTime()) {
                    $modalState = 'expired';
                } elseif ($request->isPending()) {
                    $modalState = 'unauthorized';
                } else {
                    $modalState = 'already_resolved';
                    $resolvedMessage = $this->resolvedMessageFor($request);
                }
            }
        }

        return view('livewire.notifications.respond-to-user-request', [
            'request' => $request,
            'canRespond' => $canRespond,
            'modalState' => $modalState,
            'resolvedMessage' => $resolvedMessage,
        ]);
    }

    private function finalizeInviteAccept(UserRequestDecisionService $decisions): void
    {
        $request = $this->authorizedRequest();
        if ($request === null) {
            return;
        }

        try {
            $decisions->accept($request, Auth::user());
        } catch (ValidationException $e) {
            $this->errorMessage = (string) collect($e->errors())->flatten()->first();
            $this->open = true;

            return;
        }

        $this->success(__('ui.user_requests.accepted'));
        $this->closeModal();
        $this->dispatch('database-notifications-updated', resetPagination: false);
        $this->dispatch('user-requests-updated');
    }

    private function resolvedMessageFor(UserRequest $request): string
    {
        return match ($request->status) {
            UserRequestStatus::Accepted => __('ui.user_requests.resolved_accepted'),
            UserRequestStatus::Declined => __('ui.user_requests.resolved_declined'),
            UserRequestStatus::Cancelled => __('ui.user_requests.resolved_cancelled'),
            UserRequestStatus::Expired => __('ui.user_requests.resolved_expired'),
            default => __('ui.user_requests.already_resolved'),
        };
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
