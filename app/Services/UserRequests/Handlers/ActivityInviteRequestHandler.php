<?php

declare(strict_types=1);

namespace App\Services\UserRequests\Handlers;

use App\Contracts\UserRequests\UserRequestHandler;
use App\Enums\UserRequestResolutionOutcome;
use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\ActivityParticipationService;
use App\Services\EventActivitySignupService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ActivityInviteRequestHandler implements UserRequestHandler
{
    public function __construct(
        private readonly ActivityParticipationService $participation,
        private readonly EventActivitySignupService $signupService,
    ) {}

    public function type(): UserRequestType
    {
        return UserRequestType::ActivityInvite;
    }

    public function assertCanSend(User $requester, ?User $recipient, ?Model $subject): void
    {
        if (! $subject instanceof Activity) {
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

        if (! $this->canInviteToActivity($requester, $subject)) {
            throw ValidationException::withMessages([
                'subject' => [__('ui.user_requests.activity_invite_unauthorized')],
            ]);
        }

        if ($subject->isCancelled() || ! $subject->isJoinableMode()) {
            throw ValidationException::withMessages([
                'subject' => [__('ui.activities.signup_blocked_not_joinable_mode')],
            ]);
        }

        $subject->loadMissing('slot.event');
        $event = $subject->slot?->event;
        if ($event !== null && $event->isCancelled()) {
            throw ValidationException::withMessages([
                'subject' => [__('ui.events.signup_blocked_event_cancelled')],
            ]);
        }

        if ($subject->participants()->where('user_id', $recipient->id)->exists()) {
            throw ValidationException::withMessages([
                'recipient' => [__('ui.activities.user_already_participant')],
            ]);
        }

        if ($subject->waitlist()->where('user_id', $recipient->id)->exists()) {
            throw ValidationException::withMessages([
                'recipient' => [__('ui.activities.user_already_on_waitlist')],
            ]);
        }

        $window = $this->signupService->activityScheduledWindow($subject);
        if ($window !== null && $window[0]->isPast()) {
            throw ValidationException::withMessages([
                'subject' => [__('ui.user_requests.activity_already_started')],
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
        $activity = $request->subject;
        $recipient = $request->recipient;

        if (! $activity instanceof Activity || $recipient === null) {
            throw ValidationException::withMessages([
                'request' => [__('ui.user_requests.invalid_request')],
            ]);
        }

        return $this->participation->participateAsInvitedUser($activity, $recipient);
    }

    private function canInviteToActivity(User $requester, Activity $activity): bool
    {
        if ($requester->canModifyEntity($activity)) {
            return true;
        }

        $activity->loadMissing('slot.event');
        $event = $activity->slot?->event;

        return $event instanceof Event && $requester->canModifyEntity($event);
    }
}
