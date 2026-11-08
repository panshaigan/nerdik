<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupMembershipService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

class GroupMembersList extends Component
{
    use Toast;

    #[Locked]
    public int $groupId;

    public function mount(int $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function removeMember(int $userId, GroupMembershipService $membership): void
    {
        $actor = Auth::user();
        $group = $this->resolveGroup();
        $member = User::query()->findOrFail($userId);

        try {
            $membership->removeMember($group, $actor, $member);
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->success(__('ui.groups.member_removed'));
    }

    public function render()
    {
        $group = $this->resolveGroup();
        $members = $group->users()
            ->where('users.is_deleted', false)
            ->with(['media', 'profile'])
            ->orderBy('nickname')
            ->get();

        return view('livewire.groups.group-members-list', [
            'group' => $group,
            'members' => $members,
            'canManage' => Auth::user()?->canModifyEntity($group) ?? false,
        ]);
    }

    private function resolveGroup(): Group
    {
        return Group::query()
            ->visibleTo(Auth::user())
            ->whereKey($this->groupId)
            ->firstOrFail();
    }
}
