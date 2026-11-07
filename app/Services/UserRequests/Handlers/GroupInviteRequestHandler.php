<?php

declare(strict_types=1);

namespace App\Services\UserRequests\Handlers;

use App\Contracts\UserRequests\UserRequestHandler;
use App\Enums\UserRequestResolutionOutcome;
use App\Enums\UserRequestType;
use App\Models\Group;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\GroupMembershipService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class GroupInviteRequestHandler implements UserRequestHandler
{
    public function __construct(
        private readonly GroupMembershipService $membership,
    ) {}

    public function type(): UserRequestType
    {
        return UserRequestType::GroupInvite;
    }

    public function assertCanSend(User $requester, ?User $recipient, ?Model $subject): void
    {
        if (! $subject instanceof Group) {
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

        if (! $requester->canModifyEntity($subject)) {
            throw ValidationException::withMessages([
                'subject' => [__('ui.user_requests.group_invite_unauthorized')],
            ]);
        }

        if ($subject->users()->whereKey($recipient->getKey())->exists()) {
            throw ValidationException::withMessages([
                'recipient' => [__('ui.groups.already_member')],
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
    }

    public function apply(UserRequest $request): ?UserRequestResolutionOutcome
    {
        $group = $request->subject;
        $recipient = $request->recipient;

        if (! $group instanceof Group || $recipient === null) {
            throw ValidationException::withMessages([
                'request' => [__('ui.user_requests.invalid_request')],
            ]);
        }

        $this->membership->addMember($group, $recipient);

        return null;
    }
}
