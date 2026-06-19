<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Collection;

use function fake;

final class SeedActivityParticipants
{
    /**
     * @param  Collection<int, User>  $allUsers
     * @param  Collection<int, Activity>  $activities
     */
    public function seedSelfHosted(Collection $allUsers, Collection $activities): void
    {
        foreach ($activities as $activity) {
            $activity->refresh();

            $maxParticipants = (int) ($activity->max_participants ?? 0);

            if ($maxParticipants === 0) {
                continue;
            }

            $count = fake()->numberBetween(3, min(8, $maxParticipants));

            $this->attachParticipants($activity, $allUsers, $count);
        }
    }

    /**
     * @param  Collection<int, User>  $allUsers
     * @param  Collection<int, Activity>  $activities
     */
    public function seedScheduledOnSlot(Collection $allUsers, Collection $activities): void
    {
        foreach ($activities as $activity) {
            $activity->refresh();

            if ($activity->slot === null) {
                continue;
            }

            if (! fake()->boolean(90)) {
                continue;
            }

            $maxParticipants = (int) ($activity->max_participants ?? 0);

            if ($maxParticipants === 0) {
                continue;
            }

            $ceiling = min(8, $maxParticipants);
            $targetFill = (int) round($ceiling * fake()->numberBetween(50, 85) / 100);
            $count = max(2, min($ceiling, max($targetFill, 2)));

            $this->attachParticipants($activity, $allUsers, $count);
        }
    }

    private function attachParticipants(Activity $activity, Collection $allUsers, int $count): void
    {
        $candidates = $allUsers
            ->filter(fn (User $user): bool => (int) $user->id !== (int) $activity->created_by)
            ->values();

        if ($candidates->count() < $count) {
            $candidates = $allUsers->values();
        }

        if ($candidates->isEmpty()) {
            return;
        }

        $selected = $candidates->random(min($count, $candidates->count()));

        foreach (collect($selected)->pluck('id') as $userId) {
            $activity->users()->syncWithoutDetaching([
                (int) $userId => ['is_absent' => false],
            ]);
        }
    }
}
