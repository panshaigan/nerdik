<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GroupVisibility;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupMembershipService
{
    public function create(User $owner, string $name, ?string $description = null): Group
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => [__('ui.groups.name_required')],
            ]);
        }

        return DB::transaction(function () use ($owner, $name, $description): Group {
            $group = Group::query()->create([
                'name' => $name,
                'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ]);

            $group->users()->attach($owner->id);

            return $group;
        });
    }

    public function addMember(Group $group, User $user): void
    {
        if ($group->users()->whereKey($user->getKey())->exists()) {
            throw ValidationException::withMessages([
                'user' => [__('ui.groups.already_member')],
            ]);
        }

        $group->users()->attach($user->id);
    }

    public function removeMember(Group $group, User $actor, User $member): void
    {
        if (! $actor->canModifyEntity($group)) {
            throw ValidationException::withMessages([
                'group' => [__('ui.groups.manage_unauthorized')],
            ]);
        }

        if ((int) $group->created_by === (int) $member->id) {
            throw ValidationException::withMessages([
                'user' => [__('ui.groups.cannot_remove_owner')],
            ]);
        }

        if (! $group->users()->whereKey($member->getKey())->exists()) {
            throw ValidationException::withMessages([
                'user' => [__('ui.groups.not_a_member')],
            ]);
        }

        $group->users()->detach($member->id);
    }

    public function leave(Group $group, User $user): void
    {
        if ((int) $group->created_by === (int) $user->id) {
            throw ValidationException::withMessages([
                'group' => [__('ui.groups.owner_cannot_leave')],
            ]);
        }

        if (! $group->users()->whereKey($user->getKey())->exists()) {
            throw ValidationException::withMessages([
                'group' => [__('ui.groups.not_a_member')],
            ]);
        }

        $group->users()->detach($user->id);
    }

    public function deleteGroup(Group $group, User $actor): void
    {
        if (! $actor->canModifyEntity($group)) {
            throw ValidationException::withMessages([
                'group' => [__('ui.groups.manage_unauthorized')],
            ]);
        }

        $group->delete();
    }

    public function update(Group $group, User $actor, string $name, ?string $description): Group
    {
        if (! $actor->canModifyEntity($group)) {
            throw ValidationException::withMessages([
                'group' => [__('ui.groups.manage_unauthorized')],
            ]);
        }

        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => [__('ui.groups.name_required')],
            ]);
        }

        $group->update([
            'name' => $name,
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
            'updated_by' => $actor->id,
        ]);

        return $group->fresh() ?? $group;
    }

    public function join(Group $group, User $user): void
    {
        if (! $group->isPublic()) {
            throw ValidationException::withMessages([
                'group' => [__('ui.groups.join_private')],
            ]);
        }

        if ($group->users()->whereKey($user->getKey())->exists()) {
            throw ValidationException::withMessages([
                'user' => [__('ui.groups.already_member')],
            ]);
        }

        $group->users()->attach($user->id);
    }

    public function updateVisibility(Group $group, User $actor, GroupVisibility $visibility): Group
    {
        if (! $actor->canModifyEntity($group)) {
            throw ValidationException::withMessages([
                'group' => [__('ui.groups.manage_unauthorized')],
            ]);
        }

        $group->update([
            'visibility' => $visibility,
            'updated_by' => $actor->id,
        ]);

        return $group->fresh() ?? $group;
    }
}
