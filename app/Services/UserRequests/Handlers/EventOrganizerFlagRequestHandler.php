<?php

declare(strict_types=1);

namespace App\Services\UserRequests\Handlers;

use App\Contracts\UserRequests\UserRequestHandler;
use App\Enums\UserRequestResolutionOutcome;
use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EventOrganizerFlagRequestHandler implements UserRequestHandler
{
    public function type(): UserRequestType
    {
        return UserRequestType::EventOrganizerFlag;
    }

    public function assertCanSend(User $requester, ?User $recipient, ?Model $subject): void
    {
        if ($subject !== null) {
            throw ValidationException::withMessages([
                'subject' => [__('ui.user_requests.organizer_flag_no_subject')],
            ]);
        }

        if ($recipient !== null) {
            throw ValidationException::withMessages([
                'recipient' => [__('ui.user_requests.organizer_flag_no_recipient')],
            ]);
        }

        if ($requester->canCreateEvents()) {
            throw ValidationException::withMessages([
                'requester' => [__('ui.user_requests.already_event_organizer')],
            ]);
        }

        $cooldownDays = (int) config('user_requests.organizer_request_cooldown_days', 30);
        $recentDeclined = UserRequest::query()
            ->where('type', UserRequestType::EventOrganizerFlag)
            ->where('requester_id', $requester->id)
            ->where('status', UserRequestStatus::Declined)
            ->where('responded_at', '>=', now()->subDays($cooldownDays))
            ->exists();

        if ($recentDeclined) {
            throw ValidationException::withMessages([
                'requester' => [__('ui.user_requests.organizer_request_cooldown', ['days' => $cooldownDays])],
            ]);
        }
    }

    public function assertCanRespond(UserRequest $request, User $actor): void
    {
        if ($actor->is_admin !== true) {
            throw ValidationException::withMessages([
                'request' => [__('ui.user_requests.respond_unauthorized')],
            ]);
        }
    }

    public function apply(UserRequest $request): ?UserRequestResolutionOutcome
    {
        $requester = $request->requester;

        if ($requester === null) {
            throw ValidationException::withMessages([
                'request' => [__('ui.user_requests.invalid_request')],
            ]);
        }

        if ($requester->canCreateEvents()) {
            return null;
        }

        $requester->update(['is_event_organizer' => true]);

        return null;
    }
}
