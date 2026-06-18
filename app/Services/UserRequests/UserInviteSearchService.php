<?php

declare(strict_types=1);

namespace App\Services\UserRequests;

use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserInviteSearchService
{
    /**
     * @return list<array{id: int, name: string, display_name: string, avatar: string}>
     */
    public function search(UserRequestType $type, int $subjectId, string $term, ?User $requester): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2 || $requester === null) {
            return [];
        }

        $excludedIds = $this->excludedUserIds($type, $subjectId, $requester);
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return User::query()
            ->whereNotIn('id', $excludedIds)
            ->where('nickname', $operator, '%'.$term.'%')
            ->orderBy('nickname')
            ->limit(10)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->nickname,
                'display_name' => $user->displayName(),
                'avatar' => $user->avatarUrl(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function excludedUserIds(UserRequestType $type, int $subjectId, User $requester): array
    {
        $excluded = [(int) $requester->id];

        if ($type === UserRequestType::ActivityInvite) {
            $activity = Activity::query()
                ->with(['participants', 'waitlist'])
                ->find($subjectId);

            if ($activity === null) {
                return $excluded;
            }

            return collect($excluded)
                ->merge($activity->participants->pluck('user_id'))
                ->merge($activity->waitlist->pluck('user_id'))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if ($type === UserRequestType::OrganizationInvite) {
            $memberIds = User::query()
                ->where('organization_id', $subjectId)
                ->pluck('id')
                ->all();

            return collect($excluded)
                ->merge($memberIds)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $excluded;
    }
}
