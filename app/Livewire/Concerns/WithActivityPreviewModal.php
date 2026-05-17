<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Domain\ActivityBadges\ActivityBadgeGroupConfig;
use App\Models\Activity;
use App\Services\ActivityParticipationService;
use App\Services\ActivityParticipationViewService;
use App\Services\EventActivitySignupService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

trait WithActivityPreviewModal
{
    public bool $activityPreviewModalOpen = false;

    public ?int $previewActivityId = null;

    public string $activityPreviewTab = 'info';

    public int $activityPreviewRefreshTick = 0;

    public function openListingActivityPreview(int $activityId): void
    {
        $this->openActivityPreview($activityId);
    }

    public function openActivityPreview(int $activityId): void
    {
        $activity = $this->previewActivityQuery($activityId)->firstOrFail();

        $this->previewActivityId = (int) $activity->id;
        $this->activityPreviewTab = 'info';
        $this->activityPreviewModalOpen = true;
    }

    public function closeActivityPreview(): void
    {
        $this->activityPreviewModalOpen = false;
        $this->previewActivityId = null;
        $this->activityPreviewTab = 'info';
    }

    public function updatedActivityPreviewTab(string $value): void
    {
        $this->activityPreviewTab = $this->normalizeActivityPreviewTab($value);
        if ($this->activityPreviewTab !== 'participation' || $this->previewActivityId === null) {
            return;
        }

        $activity = Activity::query()
            ->with('slot.event.enrollmentWindows')
            ->whereKey($this->previewActivityId)
            ->first();
        if ($activity === null || ! $this->activityHasActiveEnrollmentWindow($activity, app(EventActivitySignupService::class))) {
            $this->activityPreviewTab = 'info';
        }
    }

    public function updatedActivityPreviewModalOpen(bool $value): void
    {
        if (! $value) {
            $this->closeActivityPreview();
        }
    }

    #[On('event-plan-activity-participation-updated')]
    public function refreshPreviewFromParticipationBroadcast(int|string|null $activityId = null): void
    {
        if ($activityId === null) {
            return;
        }

        $activityId = (int) $activityId;

        if (! $this->previewActivityBelongsToParticipationBroadcast($activityId)) {
            return;
        }

        if ($this->activityPreviewModalOpen && (int) $this->previewActivityId === $activityId) {
            $this->activityPreviewRefreshTick++;
        }
    }

    public function joinPreviewActivity(ActivityParticipationService $participation): void
    {
        $activity = $this->selectedPreviewActivityOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $participation->join($activity, $user);
        $this->showPreviewParticipationTab();
        $this->toastPreviewParticipationStatus();
    }

    public function leavePreviewActivity(ActivityParticipationService $participation): void
    {
        $activity = $this->selectedPreviewActivityOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $participation->leave($activity, $user);
        $this->showPreviewParticipationTab();
        $this->toastPreviewParticipationStatus();
    }

    public function joinPreviewWaitlist(ActivityParticipationService $participation): void
    {
        $activity = $this->selectedPreviewActivityOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $participation->joinWaitlist($activity, $user);
        $this->showPreviewParticipationTab();
        $this->toastPreviewParticipationStatus();
    }

    public function leavePreviewWaitlist(ActivityParticipationService $participation): void
    {
        $activity = $this->selectedPreviewActivityOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $participation->leaveWaitlist($activity, $user);
        $this->showPreviewParticipationTab();
        $this->toastPreviewParticipationStatus();
    }

    /**
     * @return array{
     *     previewActivity: ?Activity,
     *     previewActivityBadgeItems: array<int, mixed>,
     *     previewActivityParticipation: mixed,
     *     previewActivityHasActiveEnrollmentWindow: bool,
     *     showPreviewParticipationActions: bool,
     * }
     */
    protected function resolveActivityPreviewViewData(
        ActivityParticipationViewService $participationView,
        ActivityBadgeGroupBuilder $badgeGroupBuilder,
        EventActivitySignupService $signupService,
    ): array {
        $previewActivity = $this->activityPreviewModalOpen && $this->previewActivityId !== null
            ? $this->previewActivityQuery($this->previewActivityId)
                ->withCount(['participants', 'waitlist'])
                ->first()
            : null;
        $previewActivityBadgeItems = [];
        $previewActivityParticipation = null;
        $previewActivityHasActiveEnrollmentWindow = false;
        $showPreviewParticipationActions = false;

        if ($previewActivity !== null) {
            $previewActivity->loadMissing([
                'slot.event.enrollmentWindows',
                'slot.place.parent.city',
                'slot.place.city',
                'place.parent.city',
                'place.city',
                'creator',
                'canceller',
                'activityType',
                'tags.translations',
                'tags.tagCategory',
            ]);
            $previewActivityHasActiveEnrollmentWindow = $this->activityHasActiveEnrollmentWindow($previewActivity, $signupService);
            if ($this->normalizeActivityPreviewTab($this->activityPreviewTab) === 'participation'
                && $previewActivityHasActiveEnrollmentWindow) {
                $previewActivity->loadMissing([
                    'participants.user',
                    'waitlist.user',
                ]);
            }
            $previewActivityBadgeItems = $badgeGroupBuilder->build(
                $previewActivity,
                ActivityBadgeGroupConfig::activityHero(),
            );
            $user = auth()->user();
            $previewActivityParticipation = $participationView->forShow($previewActivity, $user);
            $showPreviewParticipationActions = $this->showPreviewParticipationActions($previewActivity);
        } elseif ($this->activityPreviewModalOpen) {
            $this->closeActivityPreview();
        }

        return [
            'previewActivity' => $previewActivity,
            'previewActivityBadgeItems' => $previewActivityBadgeItems,
            'previewActivityParticipation' => $previewActivityParticipation,
            'previewActivityHasActiveEnrollmentWindow' => $previewActivityHasActiveEnrollmentWindow,
            'showPreviewParticipationActions' => $showPreviewParticipationActions,
        ];
    }

    protected function previewActivityQuery(int $activityId): Builder
    {
        return Activity::query()->whereKey($activityId);
    }

    protected function showPreviewParticipationActions(?Activity $activity): bool
    {
        return false;
    }

    protected function previewActivityBelongsToParticipationBroadcast(int $activityId): bool
    {
        return Activity::query()->whereKey($activityId)->exists();
    }

    protected function afterPreviewParticipationChanged(): void
    {
        //
    }

    private function activityHasActiveEnrollmentWindow(Activity $activity, EventActivitySignupService $signupService): bool
    {
        if (! $activity->relationLoaded('slot')) {
            $activity->loadMissing('slot.event.enrollmentWindows');
        } elseif ($activity->slot !== null && ! $activity->slot->relationLoaded('event')) {
            $activity->slot->loadMissing('event.enrollmentWindows');
        }

        $previewActivityEvent = $activity->slot?->event;

        return $previewActivityEvent !== null
            && $signupService->firstPeriodContaining($previewActivityEvent, now()) !== null;
    }

    private function normalizeActivityPreviewTab(?string $value): string
    {
        return in_array($value, ['info', 'participation'], true) ? $value : 'info';
    }

    private function selectedPreviewActivityOrFail(): Activity
    {
        abort_unless($this->previewActivityId !== null, 404);

        return $this->previewActivityQuery($this->previewActivityId)->firstOrFail();
    }

    private function showPreviewParticipationTab(): void
    {
        $this->activityPreviewTab = 'participation';
        $this->activityPreviewRefreshTick++;
        $this->afterPreviewParticipationChanged();
    }

    private function toastPreviewParticipationStatus(): void
    {
        $status = session()->pull('status');
        if (is_string($status) && $status !== '') {
            $this->info($status);
        }
    }
}
