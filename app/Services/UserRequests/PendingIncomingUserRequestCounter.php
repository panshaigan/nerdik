<?php

declare(strict_types=1);

namespace App\Services\UserRequests;

use App\Models\User;
use App\Models\UserRequest;

class PendingIncomingUserRequestCounter
{
    public function countFor(User $user): int
    {
        return UserRequest::query()
            ->pendingIncomingFor($user)
            ->count();
    }

    public function displayCountFor(User $user): ?string
    {
        $count = $this->countFor($user);

        if ($count === 0) {
            return null;
        }

        return $count > 9 ? '9+' : (string) $count;
    }
}
