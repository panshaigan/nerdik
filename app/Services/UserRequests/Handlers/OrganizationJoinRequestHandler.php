<?php

declare(strict_types=1);

namespace App\Services\UserRequests\Handlers;

use App\Contracts\UserRequests\UserRequestHandler;
use App\Enums\UserRequestResolutionOutcome;
use App\Enums\UserRequestType;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class OrganizationJoinRequestHandler implements UserRequestHandler
{
    public function type(): UserRequestType
    {
        return UserRequestType::OrganizationJoinRequest;
    }

    public function assertCanSend(User $requester, ?User $recipient, ?Model $subject): void
    {
        if (! $subject instanceof Organization) {
            throw ValidationException::withMessages([
                'subject' => [__('ui.user_requests.invalid_subject')],
            ]);
        }

        if ($recipient === null) {
            throw ValidationException::withMessages([
                'recipient' => [__('ui.user_requests.recipient_required')],
            ]);
        }

        if ((int) $recipient->id === (int) $requester->id) {
            throw ValidationException::withMessages([
                'recipient' => [__('ui.user_requests.cannot_request_self')],
            ]);
        }

        if (! $recipient->canModifyEntity($subject)) {
            throw ValidationException::withMessages([
                'recipient' => [__('ui.user_requests.organization_join_unauthorized_recipient')],
            ]);
        }

        if ((int) $requester->organization_id === (int) $subject->id) {
            throw ValidationException::withMessages([
                'subject' => [__('ui.user_requests.already_in_organization')],
            ]);
        }
    }

    public function assertCanRespond(UserRequest $request, User $actor): void
    {
        if ((int) $actor->id !== (int) $request->recipient_id) {
            throw ValidationException::withMessages([
                'request' => [__('ui.user_requests.respond_unauthorized')],
            ]);
        }

        $organization = $request->subject;
        if (! $organization instanceof Organization || ! $actor->canModifyEntity($organization)) {
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

        $organization = $request->subject;
        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages([
                'request' => [__('ui.user_requests.invalid_request')],
            ]);
        }

        $requester->update(['organization_id' => $organization->id]);

        return null;
    }
}
