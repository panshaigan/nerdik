<?php

declare(strict_types=1);

namespace App\Services\UserRequests;

use App\Enums\UserRequestType;
use App\Models\User;
use App\Models\UserRequest;

class PendingIncomingUserRequestCounter
{
    public function countFor(User $user): int
    {
        return UserRequest::query()
            ->pending()
            ->where(function ($query) use ($user): void {
                $query->where('recipient_id', $user->id);

                if ($user->is_admin) {
                    $query->orWhere(function ($adminQuery): void {
                        $adminQuery
                            ->where('type', UserRequestType::EventOrganizerFlag)
                            ->whereNull('recipient_id');
                    });
                }
            })
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
