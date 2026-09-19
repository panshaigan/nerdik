<?php

declare(strict_types=1);

namespace App\Livewire\UserRequests;

use App\Models\UserRequest;
use App\Services\UserRequests\UserRequestSubjectLabelResolver;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class IncomingUserRequestList extends Component
{
    #[On('user-request-sent')]
    #[On('user-requests-updated')]
    #[On('database-notifications-updated')]
    public function refreshList(): void
    {
        //
    }

    public function respond(int $requestId): void
    {
        $this->dispatch('open-user-request-modal', requestId: $requestId);
    }

    public function render(UserRequestSubjectLabelResolver $labels)
    {
        $user = Auth::user();
        $requests = collect();

        if ($user !== null) {
            $requests = UserRequest::query()
                ->pendingIncomingFor($user)
                ->with(['requester', 'subject'])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        }

        return view('livewire.user-requests.incoming-user-request-list', [
            'requests' => $requests,
            'labels' => $labels,
        ]);
    }
}
