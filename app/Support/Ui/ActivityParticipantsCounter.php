<?php

declare(strict_types=1);

namespace App\Support\Ui;

final class ActivityParticipantsCounter
{
    public function shouldShow(?int $maxParticipants, int $filled): bool
    {
        if ($maxParticipants !== null) {
            return true;
        }

        return $filled > $this->uncappedThreshold();
    }

    public function label(?int $maxParticipants, int $filled): string
    {
        if ($maxParticipants !== null) {
            return __('ui.browse.participants_filled_max', [
                'filled' => $filled,
                'max' => $maxParticipants,
            ]);
        }

        return (string) $filled;
    }

    public function uncappedThreshold(): int
    {
        return (int) config('activities.uncapped_participants_counter_threshold', 6);
    }
}
