<?php

declare(strict_types=1);

namespace App\Livewire\UserRequests;

use App\Models\UserRequest;
use App\Services\UserRequests\UserRequestService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class OutgoingUserRequestList extends Component
{
    use Toast;

    #[On('user-request-sent')]
    #[On('database-notifications-updated')]
    public function refreshList(): void
    {
        //
    }

    public function cancel(int $requestId, UserRequestService $requests): void
    {
        $request = UserRequest::query()->findOrFail($requestId);
        $requests->cancel($request, Auth::user());

        $this->success(__('ui.user_requests.cancelled'));
        $this->dispatch('database-notifications-updated', resetPagination: false);
    }

    public function render()
    {
        $requests = UserRequest::query()
            ->with(['recipient', 'subject'])
            ->pending()
            ->where('requester_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('livewire.user-requests.outgoing-user-request-list', [
            'requests' => $requests,
        ]);
    }
}
