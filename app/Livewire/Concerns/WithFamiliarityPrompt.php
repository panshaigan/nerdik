<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\FamiliarityLevel;
use App\Models\Activity;
use App\Services\ActivityFamiliarityService;
use App\Services\ActivityParticipationService;
use Illuminate\Validation\ValidationException;

trait WithFamiliarityPrompt
{
    public bool $familiarityModalOpen = false;

    public ?int $familiarityActivityId = null;

    public string $familiarityPendingAction = '';

    public ?int $familiarityInviteRequestId = null;

    /** @var array<string, string|null> */
    public array $familiarityAnswers = [];

    /** @var list<array{key: string, kind: string, subject_type: string, subject_id: int, label: string, category_key: string|null, level: string|null}> */
    public array $familiaritySubjects = [];

    /**
     * @param  callable(?array): void  $runWithoutPrompt
     */
    protected function beginFamiliarityOrRun(
        Activity $activity,
        string $pendingAction,
        callable $runWithoutPrompt,
        ?int $inviteRequestId = null,
    ): void {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $familiarity = app(ActivityFamiliarityService::class);

        if (! $activity->collect_familiarity || ! $familiarity->activityHasFamiliaritySubjects($activity)) {
            $runWithoutPrompt(null);

            return;
        }

        $subjects = $familiarity->subjectsForActivity($activity, $user);
        $this->familiaritySubjects = $subjects;
        $this->familiarityAnswers = [];
        foreach ($subjects as $subject) {
            $this->familiarityAnswers[$subject['key']] = $subject['level'];
        }
        $this->familiarityActivityId = (int) $activity->id;
        $this->familiarityPendingAction = $pendingAction;
        $this->familiarityInviteRequestId = $inviteRequestId;
        $this->familiarityModalOpen = true;
    }

    public function updatedFamiliarityModalOpen(bool $value): void
    {
        if (! $value) {
            $this->closeFamiliarityPrompt();
        }
    }

    public function closeFamiliarityPrompt(): void
    {
        $this->familiarityModalOpen = false;
        $this->familiarityActivityId = null;
        $this->familiarityPendingAction = '';
        $this->familiarityInviteRequestId = null;
        $this->familiarityAnswers = [];
        $this->familiaritySubjects = [];
    }

    public function submitFamiliarityPrompt(
        ActivityFamiliarityService $familiarityService,
        ActivityParticipationService $participation,
    ): void {
        $this->completeFamiliarityPrompt($familiarityService, $participation, skip: false);
    }

    public function skipFamiliarityPrompt(
        ActivityFamiliarityService $familiarityService,
        ActivityParticipationService $participation,
    ): void {
        $this->completeFamiliarityPrompt($familiarityService, $participation, skip: true);
    }

    protected function completeFamiliarityPrompt(
        ActivityFamiliarityService $familiarityService,
        ActivityParticipationService $participation,
        bool $skip,
    ): void {
        $user = auth()->user();
        abort_unless($user !== null, 403);
        abort_unless($this->familiarityActivityId !== null && $this->familiarityPendingAction !== '', 404);

        $activity = Activity::query()->whereKey($this->familiarityActivityId)->firstOrFail();
        $pendingAction = $this->familiarityPendingAction;
        $inviteRequestId = $this->familiarityInviteRequestId;

        $snapshot = null;
        if (! $skip) {
            try {
                $snapshot = $familiarityService->applyAnswers($user, $activity, $this->familiarityAnswers);
            } catch (ValidationException $e) {
                throw $e;
            }
        }

        $this->closeFamiliarityPrompt();
        $this->afterFamiliarityCollected($pendingAction, $activity, $snapshot, $participation, $inviteRequestId);
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
        //
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function familiarityLevelOptions(): array
    {
        return FamiliarityLevel::radioOptions();
    }
}
