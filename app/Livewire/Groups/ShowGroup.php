<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Services\GroupMembershipService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

class ShowGroup extends Component
{
    use Toast;

    #[Locked]
    public int $groupId;

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

    public function hydrate(): void
    {
        Group::query()->visibleTo(Auth::user())->whereKey($this->groupId)->firstOrFail();
    }

    public function mount(int $groupId): void
    {
        $group = Group::query()->visibleTo(Auth::user())->whereKey($groupId)->firstOrFail();
        $this->groupId = (int) $group->id;
        $this->tab = $this->normalizeTab($this->tab);

        if ($this->tab === 'discover' && ! Auth::user()?->canModifyEntity($group)) {
            $this->tab = 'calendar';
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
        $group = $this->resolveGroup();

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
            ->whereKey($this->groupId)
            ->firstOrFail();
    }

    private function normalizeTab(?string $value): string
    {
        return in_array($value, ['calendar', 'members', 'discover', 'location', 'tags'], true)
            ? $value
            : 'calendar';
    }
}
