<?php

declare(strict_types=1);

namespace App\Services\UserRequests;

use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserRequestService
{
    public function __construct(
        private readonly UserRequestHandlerRegistry $handlers,
        private readonly UserRequestExpirationResolver $expirationResolver,
        private readonly UserRequestNotifier $notifier,
    ) {}

    public function send(
        UserRequestType $type,
        User $requester,
        ?User $recipient,
        ?Model $subject,
        ?string $message = null,
    ): UserRequest {
        $handler = $this->handlers->get($type);
        $handler->assertCanSend($requester, $recipient, $subject);

        $normalizedMessage = $this->normalizeMessage($message);

        $this->assertNoDuplicatePending($type, $requester, $recipient, $subject);

        $expiresAt = $this->expirationResolver->resolve($type, $subject);

        $request = DB::transaction(function () use ($type, $requester, $recipient, $subject, $normalizedMessage, $expiresAt): UserRequest {
            $attributes = [
                'type' => $type,
                'status' => UserRequestStatus::Pending,
                'requester_id' => $requester->id,
                'recipient_id' => $recipient?->id,
                'message' => $normalizedMessage,
                'expires_at' => $expiresAt,
            ];

            if ($subject !== null) {
                $attributes['subject_type'] = $subject->getMorphClass();
                $attributes['subject_id'] = $subject->getKey();
            }

            return UserRequest::query()->create($attributes);
        });

        $this->notifier->notifyReceived($request->fresh(['requester', 'recipient', 'subject']));

        return $request;
    }

    public function cancel(UserRequest $request, User $actor): UserRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => [__('ui.user_requests.already_resolved')],
            ]);
        }

        if ((int) $actor->id !== (int) $request->requester_id) {
            throw ValidationException::withMessages([
                'request' => [__('ui.user_requests.cancel_unauthorized')],
            ]);
        }

        $request->update([
            'status' => UserRequestStatus::Cancelled,
            'responded_at' => now(),
            'responded_by_id' => $actor->id,
        ]);

        $fresh = $request->fresh(['requester', 'recipient', 'subject']);
        $this->notifier->notifyResolved($fresh);

        return $fresh;
    }

    private function normalizeMessage(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $trimmed = trim($message);
        if ($trimmed === '') {
            return null;
        }

        $max = (int) config('user_requests.message_max_length', 500);
        if (mb_strlen($trimmed) > $max) {
            throw ValidationException::withMessages([
                'message' => [__('ui.user_requests.message_too_long', ['max' => $max])],
            ]);
        }

        return $trimmed;
    }

    private function assertNoDuplicatePending(
        UserRequestType $type,
        User $requester,
        ?User $recipient,
        ?Model $subject,
    ): void {
        $query = UserRequest::query()
            ->pending()
            ->where('type', $type)
            ->where('requester_id', $requester->id);

        if ($recipient !== null) {
            $query->where('recipient_id', $recipient->id);
        } else {
            $query->whereNull('recipient_id');
        }

        if ($subject !== null) {
            $query->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey());
        } else {
            $query->whereNull('subject_type')->whereNull('subject_id');
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'request' => [__('ui.user_requests.duplicate_pending')],
            ]);
        }
    }
}
