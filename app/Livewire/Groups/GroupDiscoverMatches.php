<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Enums\UserRequestType;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupCandidateDiscoveryService;
use App\Services\UserRequests\UserRequestService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class GroupDiscoverMatches extends Component
{
    use Toast;
    use WithPagination;

    #[Locked]
    public int $groupId;

    protected string $paginationTheme = 'tailwind';

    public function mount(int $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function invite(int $userId, UserRequestService $requests): void
    {
        $actor = Auth::user();
        if ($actor === null) {
            return;
        }

        $group = $this->resolveGroup();
        $recipient = User::query()
            ->where('is_deleted', false)
            ->find($userId);

        if ($recipient === null) {
            return;
        }

        try {
            $requests->send(
                UserRequestType::GroupInvite,
                $actor,
                $recipient,
                $group,
                null,
            );
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->success(__('ui.user_requests.sent'));
        $this->resetPage();
    }

    #[On('group-location-updated')]
    #[On('group-tags-updated')]
    public function refreshMatches(): void
    {
        $this->resetPage();
    }

    public function render(GroupCandidateDiscoveryService $discovery)
    {
        $group = $this->resolveGroup()->load('tags');
        $actor = Auth::user();

        $hasCriteria = $discovery->hasDiscoveryCriteria($group);

        $candidates = (! $hasCriteria || $actor === null)
            ? $discovery->emptyCandidates()
            : $discovery->candidates($group, $actor);

        return view('livewire.groups.group-discover-matches', [
            'group' => $group,
            'hasCriteria' => $hasCriteria,
            'candidates' => $candidates,
            'canManage' => $actor?->canModifyEntity($group) ?? false,
        ]);
    }

    private function resolveGroup(): Group
    {
        return Group::query()
            ->memberContentVisibleTo(Auth::user())
            ->whereKey($this->groupId)
            ->firstOrFail();
    }
}
