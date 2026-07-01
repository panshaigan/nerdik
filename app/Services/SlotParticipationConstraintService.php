<?php

namespace App\Services;

use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\ActivityLotteryDraw;
use App\Models\Slot;
use Illuminate\Support\Collection;

class SlotParticipationConstraintService
{
    /**
     * @param  Collection<int, Slot>  $slots
     * @return Collection<int, Slot>
     */
    public function constrainingSlots(Collection $slots): Collection
    {
        return $slots->filter(fn (Slot $slot): bool => $slot->forcesParticipationSettings())->values();
    }

    /**
     * @param  Collection<int, Slot>  $forcingSlots
     * @return list<string>
     */
    public function allowedParticipationModes(Collection $forcingSlots): array
    {
        return $forcingSlots
            ->map(fn (Slot $slot): ?string => $slot->forcedParticipationMode()?->value)
            ->filter(fn (?string $mode): bool => $mode !== null)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Slot>  $forcingSlots
     * @return list<int>
     */
    public function allowedLotteryDrawHoursForMode(Collection $forcingSlots, string $selectedMode): array
    {
        if ($selectedMode !== ParticipationMode::Lottery->value) {
            return [];
        }

        return $forcingSlots
            ->filter(fn (Slot $slot): bool => $slot->forcedParticipationMode() === ParticipationMode::Lottery)
            ->map(fn (Slot $slot): int => (int) ($slot->lottery_draw_in_hours ?? 24))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Slot>  $forcingSlots
     * @return list<bool>
     */
    public function allowedAllowsObserversForMode(Collection $forcingSlots, string $selectedMode): array
    {
        $mode = ParticipationMode::tryFrom($selectedMode);
        if ($mode === null) {
            return [];
        }

        return $forcingSlots
            ->filter(fn (Slot $slot): bool => $slot->forcedParticipationMode() === $mode)
            ->map(fn (Slot $slot): bool => (bool) $slot->allows_observers)
            ->unique()
            ->values()
            ->all();
    }

    public function activityMatchesSlot(Activity $activity, Slot $slot): bool
    {
        $payload = $slot->participationSettingsPayload();
        if ($payload === null) {
            return true;
        }

        if ($activity->participationMode() !== $payload['participation_mode']) {
            return false;
        }

        if ($payload['participation_mode'] === ParticipationMode::Lottery) {
            $activityHours = (int) ($activity->lottery_draw_in_hours ?? 0);
            $slotHours = (int) ($payload['lottery_draw_in_hours'] ?? 0);
            if ($activityHours !== $slotHours) {
                return false;
            }
        }

        return (bool) $activity->allows_observers === $payload['allows_observers'];
    }

    public function applyForcedSettingsToActivity(Slot $slot, Activity $activity): void
    {
        $payload = $slot->participationSettingsPayload();
        if ($payload === null) {
            return;
        }

        $participationModeChanged = $activity->participation_mode !== $payload['participation_mode'];
        $lotteryDrawHoursChanged = (int) ($activity->lottery_draw_in_hours ?? 0)
            !== (int) ($payload['lottery_draw_in_hours'] ?? 0);

        $update = [
            'participation_mode' => $payload['participation_mode'],
            'allows_observers' => $payload['allows_observers'],
            'lottery_draw_in_hours' => $payload['participation_mode'] === ParticipationMode::Lottery
                ? $payload['lottery_draw_in_hours']
                : null,
        ];

        if ($participationModeChanged || $lotteryDrawHoursChanged) {
            $update['lottery_resolved_at'] = null;
        }

        $activity->update($update);

        if ($participationModeChanged || $lotteryDrawHoursChanged) {
            ActivityLotteryDraw::query()->where('activity_id', $activity->id)->delete();
        }
    }

    /**
     * @param  Collection<int, Slot>  $forcingSlots
     * @return array{
     *     constrained: bool,
     *     allowed_modes: list<string>,
     *     allowed_lottery_draw_hours: list<int>,
     *     allowed_allows_observers: list<bool>,
     *     lottery_draw_hours_locked: bool,
     *     allows_observers_locked: bool,
     * }
     */
    public function resolveConstraintState(
        Collection $forcingSlots,
        string $currentMode,
        ?int $currentLotteryDrawHours,
        bool $currentAllowsObservers,
    ): array {
        if ($forcingSlots->isEmpty()) {
            return [
                'constrained' => false,
                'allowed_modes' => ParticipationMode::values(),
                'allowed_lottery_draw_hours' => [],
                'allowed_allows_observers' => [],
                'lottery_draw_hours_locked' => false,
                'allows_observers_locked' => false,
            ];
        }

        $allowedModes = $this->allowedParticipationModes($forcingSlots);
        $mode = in_array($currentMode, $allowedModes, true)
            ? $currentMode
            : ($allowedModes[0] ?? ParticipationMode::Open->value);

        $allowedLotteryHours = $this->allowedLotteryDrawHoursForMode($forcingSlots, $mode);
        $allowedObservers = $this->allowedAllowsObserversForMode($forcingSlots, $mode);

        return [
            'constrained' => true,
            'allowed_modes' => $allowedModes,
            'allowed_lottery_draw_hours' => $allowedLotteryHours,
            'allowed_allows_observers' => $allowedObservers,
            'lottery_draw_hours_locked' => count($allowedLotteryHours) === 1,
            'allows_observers_locked' => count($allowedObservers) === 1,
        ];
    }
}
