<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupMembershipService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;

class ShowGroup extends Component
{
    use Toast;

    public string $groupSlug;

    public function mount(string $groupSlug): void
    {
        $this->groupSlug = $groupSlug;
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

    public function leave(GroupMembershipService $membership): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $group = $this->resolveGroup();

        try {
            $membership->leave($group, $user);
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->redirect(route('groups.index'), navigate: true);
    }

    public function deleteGroup(GroupMembershipService $membership): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $group = $this->resolveGroup();

        try {
            $membership->deleteGroup($group, $user);
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->redirect(route('groups.index'), navigate: true);
    }

    public function render()
    {
        $group = $this->resolveGroup()->load(['users']);

        return view('livewire.groups.show-group', [
            'group' => $group,
            'canManage' => Auth::user()?->canModifyEntity($group) ?? false,
            'isOwner' => (int) $group->created_by === (int) Auth::id(),
        ]);
    }

    private function resolveGroup(): Group
    {
        return Group::query()
            ->visibleTo(Auth::user())
            ->where('slug', $this->groupSlug)
            ->firstOrFail();
    }
}
