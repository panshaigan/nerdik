<?php

declare(strict_types=1);

namespace App\Services\UserRequests;

use App\Enums\UserRequestStatus;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserRequestDecisionService
{
    public function __construct(
        private readonly UserRequestHandlerRegistry $handlers,
        private readonly UserRequestNotifier $notifier,
    ) {}

    public function accept(UserRequest $request, User $actor): UserRequest
    {
        return $this->resolve($request, $actor, UserRequestStatus::Accepted, null);
    }

    public function decline(UserRequest $request, User $actor, ?string $note = null): UserRequest
    {
        return $this->resolve($request, $actor, UserRequestStatus::Declined, $note);
    }

    private function resolve(
        UserRequest $request,
        User $actor,
        UserRequestStatus $targetStatus,
        ?string $declineNote,
    ): UserRequest {
        return DB::transaction(function () use ($request, $actor, $targetStatus, $declineNote): UserRequest {
            $locked = UserRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'request' => [__('ui.user_requests.already_resolved')],
                ]);
            }

            if ($locked->isExpiredByTime()) {
                $locked->update([
                    'status' => UserRequestStatus::Expired,
                    'responded_at' => now(),
                ]);

                throw ValidationException::withMessages([
                    'request' => [__('ui.user_requests.expired')],
                ]);
            }

            $handler = $this->handlers->get($locked->type);
            $handler->assertCanRespond($locked, $actor);

            $resolutionOutcome = null;
            if ($targetStatus === UserRequestStatus::Accepted) {
                $locked->loadMissing('subject', 'requester', 'recipient');
                $resolutionOutcome = $handler->apply($locked);
            }

            $locked->update([
                'status' => $targetStatus,
                'responded_at' => now(),
                'responded_by_id' => $actor->id,
                'resolution_outcome' => $resolutionOutcome,
            ]);

            $fresh = $locked->fresh(['requester', 'recipient', 'respondedBy', 'subject']);
            $this->notifier->notifyResolved($fresh, $declineNote);

            return $fresh;
        });
    }
}
