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

    public string $tab = 'calendar';

    /**
     * Tab keys whose panel Livewire children stay mounted after first visit.
     *
     * @var list<string>
     */
    public array $mountedTabs = [];

    protected array $queryString = [
        'tab' => ['except' => 'calendar'],
    ];

    public function mount(string $groupSlug): void
    {
        $this->groupSlug = $groupSlug;
        $this->tab = $this->normalizeTab($this->tab);

        if ($this->tab === 'discover') {
            $group = $this->resolveGroup();
            if (! Auth::user()?->canModifyEntity($group)) {
                $this->tab = 'calendar';
            }
        }

        $this->mountedTabs = [$this->tab];
    }

    public function updatedTab(string $value): void
    {
        $this->tab = $this->normalizeTab($value);

        if (! in_array($this->tab, $this->mountedTabs, true)) {
            $this->mountedTabs[] = $this->tab;

            return;
        }

        $this->skipRender();
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

    private function normalizeTab(?string $value): string
    {
        return in_array($value, ['calendar', 'members', 'discover', 'location', 'tags'], true)
            ? $value
            : 'calendar';
    }
}
