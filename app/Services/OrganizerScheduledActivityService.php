<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizerScheduledActivityService
{
    public function __construct(
        private readonly SlotFormService $slotForms,
        private readonly ActivityHostingModeService $hostingModes,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{activity: Activity, slot: Slot}
     */
    public function create(Event $event, User $actor, array $validated): array
    {
        if ($event->isCancelled()) {
            throw ValidationException::withMessages([
                'name' => [__('ui.events.signup_blocked_cancelled')],
            ]);
        }

        $startUtc = parse_datetime_to_utc((string) $validated['starts_at']);
        $endUtc = parse_datetime_to_utc((string) $validated['ends_at']);

        if ($startUtc === null || $endUtc === null) {
            throw ValidationException::withMessages([
                'starts_at' => [__('validation.date', ['attribute' => 'starts_at'])],
            ]);
        }

        if ($endUtc->lt($startUtc)) {
            throw ValidationException::withMessages([
                'ends_at' => [__('ui.slots.ends_after_start')],
            ]);
        }

        $venuePlaceId = isset($validated['venue_place_id']) && $validated['venue_place_id'] !== ''
            ? (int) $validated['venue_place_id']
            : null;
        $newRoomName = isset($validated['new_room_name']) ? (string) $validated['new_room_name'] : '';

        $this->slotForms->assertVenuePresentWhenEventHasPlaces($event, $venuePlaceId);
        $resolvedPlaceId = $this->slotForms->resolveSlotPlaceId($event, $venuePlaceId, $newRoomName);

        $participationMode = ParticipationMode::tryFrom((string) $validated['participation_mode'])
            ?? ParticipationMode::Open;

        if ($participationMode === ParticipationMode::Lottery) {
            $lotteryHours = $validated['lottery_draw_in_hours'] ?? null;
            if ($lotteryHours === null || $lotteryHours === '') {
                throw ValidationException::withMessages([
                    'lottery_draw_in_hours' => [__('validation.required', ['attribute' => 'lottery_draw_in_hours'])],
                ]);
            }
        }

        $durationMinutes = $this->durationMinutesBetween($startUtc, $endUtc);

        return DB::transaction(function () use (
            $event,
            $actor,
            $validated,
            $startUtc,
            $endUtc,
            $resolvedPlaceId,
            $participationMode,
            $durationMinutes,
        ): array {
            $activity = Activity::create([
                'name' => trim((string) $validated['name']),
                'activity_type_id' => (int) $validated['activity_type_id'],
                'created_by' => $actor->id,
                'participation_mode' => $participationMode,
                'lottery_draw_in_hours' => $participationMode === ParticipationMode::Lottery
                    ? (int) $validated['lottery_draw_in_hours']
                    : null,
                'allows_observers' => filter_var($validated['allows_observers'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'collect_familiarity' => filter_var($validated['collect_familiarity'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'duration_in_minutes' => $durationMinutes,
                'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            ]);

            $this->hostingModes->markScheduledOnEvent($activity);

            $slot = Slot::create([
                'event_id' => $event->id,
                'created_by' => $actor->id,
                'name' => trim((string) $validated['name']),
                'starts_at' => $startUtc->toDateTimeString(),
                'ends_at' => $endUtc->toDateTimeString(),
                'place_id' => $resolvedPlaceId,
                'activity_id' => $activity->id,
                'requires_approval' => false,
                'forces_participation_settings' => false,
            ]);

            $slot->setActivityTypes([(int) $validated['activity_type_id']]);

            return [
                'activity' => $activity->fresh(),
                'slot' => $slot->fresh(['activity']),
            ];
        });
    }

    private function durationMinutesBetween(CarbonInterface $startUtc, CarbonInterface $endUtc): int
    {
        $minutes = (int) $startUtc->diffInMinutes($endUtc);

        if ($minutes <= 0) {
            throw ValidationException::withMessages([
                'ends_at' => [__('ui.slots.ends_after_start')],
            ]);
        }

        return $minutes;
    }
}
