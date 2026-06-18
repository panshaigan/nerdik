<?php

declare(strict_types=1);

namespace App\Contracts\UserRequests;

use App\Enums\UserRequestResolutionOutcome;
use App\Enums\UserRequestType;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Database\Eloquent\Model;

interface UserRequestHandler
{
    public function type(): UserRequestType;

    public function assertCanSend(User $requester, ?User $recipient, ?Model $subject): void;

    public function assertCanRespond(UserRequest $request, User $actor): void;

    /**
     * @return UserRequestResolutionOutcome|null Outcome for activity invites; null for other types.
     */
    public function apply(UserRequest $request): ?UserRequestResolutionOutcome;
}
