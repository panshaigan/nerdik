<?php

namespace App\Livewire\Activities;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Domain\ActivityBadges\ActivityBadgeGroupConfig;
use App\Livewire\Concerns\WithUiConfirmModal;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use App\Services\ActivityHostingModeService;
use App\Services\ActivityParticipationService;
use App\Services\ActivityParticipationViewService;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class ShowActivity extends Component
{
    use Toast;
    use WithUiConfirmModal;

    public int $activityId;

    public ?string $cancelReason = null;

    public string $tab = 'info';

    /** Bumped when another client changes roster while this viewer has the Participation tab open. */
    public int $participationBroadcastRefreshTick = 0;

    protected array $queryString = [
        'tab' => ['except' => 'info'],
    ];

    public function mount(Activity $activity): void
    {
        $this->activityId = $activity->id;
        $this->tab = $this->normalizeTab($this->tab);
    }

    public function updatedTab(string $value): void
    {
        $this->tab = $this->normalizeTab($value);
    }

    #[On('activity-participation-updated')]
    public function refreshParticipationFromBroadcast(int|string|null $activityId = null): void
    {
        if ($activityId === null || (int) $activityId !== $this->activityId) {
            return;
        }

        if ($this->tab !== 'participation') {
            return;
        }

        $this->participationBroadcastRefreshTick++;
    }

    public function confirmDeleteActivity(): void
    {
        $this->openConfirm('delete_activity', __('ui.activities.delete'), __('ui.activities.delete_confirm'));
    }

    public function confirmCancelActivity(): void
    {
        $this->openConfirm('cancel_activity', __('ui.activities.cancel_action'), __('ui.activities.cancel_confirm'));
    }

    public function confirmReopenActivity(): void
    {
        $this->openConfirm('reopen_activity', __('ui.activities.reopen_action'), __('ui.activities.reopen_confirm'));
    }

    public function confirmMoveParticipantToWaitlist(int $participantId): void
    {
        $this->openConfirm(
            'move_participant_to_waitlist',
            __('ui.activities.move_to_waitlist'),
            __('ui.activities.move_to_waitlist_confirm'),
            $participantId,
        );
    }

    public function confirmRemoveParticipant(int $participantId): void
    {
        $this->openConfirm(
            'remove_participant',
            __('ui.activities.remove_participant'),
            __('ui.activities.remove_participant_confirm'),
            $participantId,
        );
    }

    public function runConfirmedAction(ActivityHostingModeService $hostingModes, ActivityParticipationService $participation): void
    {
        $action = $this->pendingAction;
        $participantId = $this->pendingParticipantId;
        $this->closeConfirm();

        if ($action === null) {
            return;
        }

        match ($action) {
            'delete_activity' => $this->deleteActivity(),
            'cancel_activity' => $this->cancel($hostingModes),
            'reopen_activity' => $this->reopen($hostingModes),
            'move_participant_to_waitlist' => $participantId !== null ? $this->moveParticipantToWaitlist($participantId, $participation) : null,
            'remove_participant' => $participantId !== null ? $this->removeParticipant($participantId, $participation) : null,
            default => null,
        };
    }

    public function cancel(ActivityHostingModeService $hostingModes): void
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();
        abort_unless(auth()->user()?->canModifyEntity($activity), 403);

        $reason = $this->cancelReason !== null ? trim($this->cancelReason) : null;
        if ($reason === '') {
            $reason = null;
        }
        if ($reason !== null && mb_strlen($reason) > 1000) {
            $this->addError('cancelReason', __('validation.max.string', [
                'attribute' => 'cancel_reason',
                'max' => 1000,
            ]));

            return;
        }

        $hostingModes->cancel($activity, auth()->user(), $reason);
        $this->cancelReason = null;
        $this->success(__('ui.activities.cancelled_status'));
    }

    public function reopen(ActivityHostingModeService $hostingModes): void
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();
        $user = auth()->user();
        abort_unless($user instanceof User && $user->canModifyEntity($activity), 403);

        $hostingModes->reopen($activity, $user);
        $this->success(__('ui.activities.reopened_status'));
    }

    public function deleteActivity(): void
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();
        abort_unless(auth()->user()?->canModifyEntity($activity), 403);

        if (! $activity->allowsHardDeletion()) {
            $this->warning(__('ui.activities.delete_forbidden_requires_cancel'));

            return;
        }

        $activity->delete();
        $this->success(__('ui.activities.deleted_status'));
        $this->redirect(route('search.index'), navigate: true);
    }

    public function addInterest(): void
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $user->interestedActivities()->syncWithoutDetaching([$activity->id]);
        $eventId = $activity->slot?->event_id;
        if ($eventId !== null) {
            $user->interestedEvents()->syncWithoutDetaching([(int) $eventId]);
        }
        $this->success(__('ui.interests.added_activity'));
    }

    public function removeInterest(): void
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $user->interestedActivities()->detach($activity->id);
        $this->warning(__('ui.interests.removed_activity'));
    }

    public function join(ActivityParticipationService $participation): void
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $participation->join($activity, $user);
        $this->toastFromSessionStatus();
    }

    public function leave(ActivityParticipationService $participation): void
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $participation->leave($activity, $user);
        $this->toastFromSessionStatus();
    }

    public function joinWaitlist(ActivityParticipationService $participation): void
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $participation->joinWaitlist($activity, $user);
        $this->toastFromSessionStatus();
    }

    public function leaveWaitlist(ActivityParticipationService $participation): void
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $participation->leaveWaitlist($activity, $user);
        $this->toastFromSessionStatus();
    }

    public function approveWaitlist(int $entryId, ActivityParticipationService $participation): void
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();
        $entry = ActivityWaitlistEntry::query()->whereKey($entryId)->firstOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $participation->approveWaitlistEntry($activity, $entry, $user);
        $this->toastFromSessionStatus();
    }

    public function markParticipantAbsent(int $participantId, ActivityParticipationService $participation): void
    {
        $participant = ActivityUser::query()->whereKey($participantId)->firstOrFail();
        $this->assertParticipantBelongsToActivity($participant);
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $participation->markParticipantAbsent($participant, $user);
        $this->toastFromSessionStatus();
    }

    public function unmarkParticipantAbsent(int $participantId, ActivityParticipationService $participation): void
    {
        $participant = ActivityUser::query()->whereKey($participantId)->firstOrFail();
        $this->assertParticipantBelongsToActivity($participant);
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $participation->unmarkAbsent($participant, $user);
        $this->toastFromSessionStatus();
    }

    public function removeParticipant(int $participantId, ActivityParticipationService $participation): void
    {
        $participant = ActivityUser::query()->whereKey($participantId)->firstOrFail();
        $this->assertParticipantBelongsToActivity($participant);
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $participation->removeParticipant($participant, $user);
        $this->toastFromSessionStatus();
    }

    public function moveParticipantToWaitlist(int $participantId, ActivityParticipationService $participation): void
    {
        $participant = ActivityUser::query()->whereKey($participantId)->firstOrFail();
        $this->assertParticipantBelongsToActivity($participant);
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $participation->moveParticipantToWaitlist($participant, $user);
        $this->toastFromSessionStatus();
    }

    public function render(ActivityParticipationViewService $participationView, ActivityBadgeGroupBuilder $badgeGroupBuilder)
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();

        $activity->load([
            'creator',
            'canceller',
            'activityType',
            'tags.translations',
            'tags.tagCategory',
            'participants.user',
            'waitlist.user',
            'slot.event.enrollmentWindows',
            'slot.place.parent.city',
            'slot.place.city',
            'place.parent.city',
            'place.city',
        ]);

        $vm = $participationView->forShow($activity, auth()->user());
        $interestedPeopleCount = (int) $activity->interestedUsers()->count();

        $badgeItems = $badgeGroupBuilder->build($activity, ActivityBadgeGroupConfig::activityHero());

        return view('livewire.activities.show-activity', [
            'activity' => $activity,
            'canHardDeleteActivity' => $activity->allowsHardDeletion(),
            'badgeItems' => $badgeItems,
            'isParticipant' => $vm->isParticipant,
            'onWaitlist' => $vm->onWaitlist,
            'canJoin' => $vm->canJoin,
            'isFull' => $vm->isFull,
            'hasInterest' => $vm->hasInterest,
            'interestedPeopleCount' => $interestedPeopleCount,
            'canManageActivity' => $vm->canManageActivity,
            'signupBlockedMessage' => $vm->signupBlockedMessage,
            'stateBlockedMessage' => $vm->stateBlockedMessage,
            'activeWindowPerActivityMax' => $vm->activeWindowPerActivityMax,
            'activeWindowRemainingForActivity' => $vm->activeWindowRemainingForActivity,
            'activeWindowUserRemaining' => $vm->activeWindowUserRemaining,
        ]);
    }

    private function normalizeTab(?string $value): string
    {
        return in_array($value, ['info', 'participation'], true) ? $value : 'info';
    }

    private function assertParticipantBelongsToActivity(ActivityUser $participant): void
    {
        abort_unless((int) $participant->activity_id === $this->activityId, 404);
    }

    private function toastFromSessionStatus(): void
    {
        $status = session()->pull('status');
        if (is_string($status) && $status !== '') {
            $this->info($status);
        }
    }
}
