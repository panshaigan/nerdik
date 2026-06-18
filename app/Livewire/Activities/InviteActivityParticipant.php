<?php

declare(strict_types=1);

namespace App\Livewire\Activities;

use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\User;
use App\Services\UserRequests\UserRequestService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;

class InviteActivityParticipant extends Component
{
    use Toast;

    public int $activityId;

    public bool $modalOpen = false;

    public string $searchTerm = '';

    public ?int $selectedUserId = null;

    public string $message = '';

    public function mount(int $activityId): void
    {
        $this->activityId = $activityId;
    }

    public function openModal(): void
    {
        $this->searchTerm = '';
        $this->selectedUserId = null;
        $this->message = '';
        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->searchTerm = '';
        $this->selectedUserId = null;
        $this->message = '';
    }

    public function selectUser(int $userId): void
    {
        $this->selectedUserId = $userId;
    }

    public function send(UserRequestService $requests): void
    {
        $requester = Auth::user();
        if ($requester === null) {
            return;
        }

        $activity = Activity::query()->findOrFail($this->activityId);
        $recipient = $this->selectedUserId !== null
            ? User::query()->find($this->selectedUserId)
            : null;

        if ($recipient === null) {
            $this->error(__('ui.user_requests.invite_participant_select_user'));

            return;
        }

        try {
            $requests->send(
                UserRequestType::ActivityInvite,
                $requester,
                $recipient,
                $activity,
                $this->message !== '' ? $this->message : null,
            );
        } catch (ValidationException $e) {
            $this->error((string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->success(__('ui.user_requests.sent'));
        $this->closeModal();
        $this->dispatch('user-request-sent');
    }

    public function render()
    {
        $searchResults = $this->searchUsers();

        return view('livewire.activities.invite-activity-participant', [
            'searchResults' => $searchResults,
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function searchUsers(): Collection
    {
        $term = trim($this->searchTerm);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $activity = Activity::query()
            ->with(['participants', 'waitlist'])
            ->find($this->activityId);

        if ($activity === null) {
            return collect();
        }

        $excludedIds = collect([Auth::id()])
            ->merge($activity->participants->pluck('user_id'))
            ->merge($activity->waitlist->pluck('user_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return User::query()
            ->whereNotIn('id', $excludedIds)
            ->where('nickname', $operator, '%'.$term.'%')
            ->orderBy('nickname')
            ->limit(10)
            ->get();
    }
}
