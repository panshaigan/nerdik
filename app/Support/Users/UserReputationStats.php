<?php

declare(strict_types=1);

namespace App\Support\Users;

use App\Models\ActivityUser;
use App\Models\User;
use App\Models\UserHostBan;

final class UserReputationStats
{
    public const int MIN_HOSTS_FOR_WARNING = 2;

    public const int MIN_ABSENCES_FOR_WARNING = 3;

    public function distinctHostBanCount(User $user): int
    {
        return (int) UserHostBan::query()
            ->where('banned_user_id', $user->id)
            ->distinct()
            ->count('host_user_id');
    }

    public function absenceCount(User $user): int
    {
        return (int) ActivityUser::query()
            ->where('user_id', $user->id)
            ->where('is_absent', true)
            ->whereNull('deleted_at')
            ->count();
    }

    public function shouldShowWarning(User $user): bool
    {
        return $this->distinctHostBanCount($user) >= self::MIN_HOSTS_FOR_WARNING
            || $this->absenceCount($user) >= self::MIN_ABSENCES_FOR_WARNING;
    }

    /**
     * @return array{ban_count: int, absence_count: int, show_warning: bool}
     */
    public function forUser(User $user): array
    {
        $banCount = $this->distinctHostBanCount($user);
        $absenceCount = $this->absenceCount($user);

        return [
            'ban_count' => $banCount,
            'absence_count' => $absenceCount,
            'show_warning' => $banCount >= self::MIN_HOSTS_FOR_WARNING
                || $absenceCount >= self::MIN_ABSENCES_FOR_WARNING,
        ];
    }
}
