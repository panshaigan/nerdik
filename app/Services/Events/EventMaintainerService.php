<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventMaintainerService
{
    /**
     * @return list<array{id: int, name: string, display_name: string, avatar: string}>
     */
    public function searchCandidates(Event $event, User $actor, string $term): array
    {
        if (! $actor->canManageEventMaintainers($event)) {
            return [];
        }

        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $excluded = $this->excludedUserIds($event);

        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return User::query()
            ->whereNotIn('id', $excluded)
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

    public function addMaintainer(Event $event, User $actor, User $candidate): void
    {
        if (! $actor->canManageEventMaintainers($event)) {
            throw ValidationException::withMessages([
                'maintainer' => [__('ui.events.maintainers.manage_forbidden')],
            ]);
        }

        if ($this->isExcludedCandidate($event, $candidate)) {
            throw ValidationException::withMessages([
                'maintainer' => [__('ui.events.maintainers.cannot_add')],
            ]);
        }

        if ($event->maintainers()->where('users.id', $candidate->id)->exists()) {
            throw ValidationException::withMessages([
                'maintainer' => [__('ui.events.maintainers.already_added')],
            ]);
        }

        $event->maintainers()->attach($candidate->id, [
            'created_by' => $actor->id,
        ]);
    }

    public function removeMaintainer(Event $event, User $actor, User $maintainer): void
    {
        if (! $actor->canManageEventMaintainers($event)) {
            throw ValidationException::withMessages([
                'maintainer' => [__('ui.events.maintainers.manage_forbidden')],
            ]);
        }

        if ((int) $event->created_by === (int) $maintainer->id) {
            throw ValidationException::withMessages([
                'maintainer' => [__('ui.events.maintainers.cannot_remove_owner')],
            ]);
        }

        $event->maintainers()->detach($maintainer->id);
    }

    /**
     * Users who receive organizer notifications (owner + maintainers).
     *
     * @return Collection<int, User>
     */
    public function managingUsers(Event $event): Collection
    {
        $event->loadMissing(['creator', 'maintainers']);

        return collect([$event->creator])
            ->merge($event->maintainers)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * @return list<int>
     */
    private function excludedUserIds(Event $event): array
    {
        $excluded = [(int) $event->created_by];

        return collect($excluded)
            ->merge($event->maintainers()->pluck('users.id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isExcludedCandidate(Event $event, User $candidate): bool
    {
        return in_array((int) $candidate->id, $this->excludedUserIds($event), true);
    }
}
