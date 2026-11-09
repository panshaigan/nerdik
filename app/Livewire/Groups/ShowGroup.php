<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Enums\GroupVisibility;
use App\Models\Group;
use App\Models\Tag;
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

    public string $visibility = GroupVisibility::Private->value;

    public function mount(int $groupId): void
    {
        $group = Group::query()->visibleTo(Auth::user())->whereKey($groupId)->firstOrFail();
        $this->groupId = (int) $group->id;
        $user = Auth::user();

        if ($user !== null && $user->canModifyEntity($group)) {
            $this->visibility = $group->visibility->value;
        }

        if (! $group->canViewMemberContent($user)) {
            $this->tab = 'calendar';
            $this->mountedTabs = [];

            return;
        }

        $this->tab = $this->normalizeTab($this->tab);

        if ($this->tab === 'discover' && ! $user?->canModifyEntity($group)) {
            $this->tab = 'calendar';
        }

        $this->mountedTabs = [$this->tab];
    }

    public function join(GroupMembershipService $membership): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $group = $this->resolveGroup();

        try {
            $membership->join($group, $user);
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->success(__('ui.groups.joined'));
        $this->tab = 'calendar';
        $this->mountedTabs = ['calendar'];
    }

    public function updatedVisibility(string $value): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $visibility = GroupVisibility::tryFrom($value);
        if ($visibility === null) {
            return;
        }

        $group = $this->resolveGroup();

        if (! $user->canModifyEntity($group)) {
            return;
        }

        try {
            $updated = app(GroupMembershipService::class)->updateVisibility($group, $user, $visibility);
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());
            $this->visibility = $group->visibility->value;

            return;
        }

        $this->visibility = $updated->visibility->value;
        $this->success(__('ui.common.saved'));
    }

    public function updatedTab(string $value): void
    {
        $group = $this->resolveGroup();
        if (! $group->canViewMemberContent(Auth::user())) {
            return;
        }

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
        $group = $this->resolveGroup()
            ->loadMissing(['tags.translations'])
            ->loadCount('users');

        $user = Auth::user();
        $canViewMemberContent = $group->canViewMemberContent($user);
        $locale = app()->getLocale();

        $tagLabels = $group->tags
            ->map(static fn (Tag $tag) => $tag->displayLabel($locale))
            ->filter()
            ->values()
            ->all();

        return view('livewire.groups.show-group', [
            'group' => $group,
            'canManage' => $user?->canModifyEntity($group) ?? false,
            'isOwner' => (int) $group->created_by === (int) Auth::id(),
            'canViewMemberContent' => $canViewMemberContent,
            'tagLabels' => $tagLabels,
            'visibilityChoices' => collect(GroupVisibility::cases())
                ->map(fn (GroupVisibility $case): array => [
                    'id' => $case->value,
                    'name' => $case->label(),
                    'hint' => $case->hint(),
                ])
                ->all(),
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
