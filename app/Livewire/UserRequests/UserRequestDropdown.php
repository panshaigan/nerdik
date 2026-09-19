<?php

namespace App\Livewire\UserRequests;

use App\Models\UserRequest;
use App\Services\UserRequests\PendingIncomingUserRequestCounter;
use App\Services\UserRequests\UserRequestSubjectLabelResolver;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class UserRequestDropdown extends Component
{
    public const PREVIEW_LIMIT = 8;

    public string $variant = 'desktop';

    #[On('user-requests-updated')]
    #[On('database-notifications-updated')]
    public function refreshRequestDropdown(): void
    {
        //
    }

    public function openRequest(int $requestId): void
    {
        $user = Auth::user();
        $request = UserRequest::query()->involving($user)->findOrFail($requestId);

        $this->redirect(
            route('requests.index', ['request' => $request->id]),
            navigate: true,
        );
    }

    public function render(UserRequestSubjectLabelResolver $labels, PendingIncomingUserRequestCounter $pendingCounter)
    {
        $user = Auth::user();
        $hasAnyRequests = UserRequest::query()->involving($user)->exists();
        $requests = collect();
        $displays = [];
        $pendingBadge = null;

        if ($hasAnyRequests) {
            $requests = UserRequest::query()
                ->involving($user)
                ->with(['requester', 'recipient', 'subject'])
                ->latest()
                ->limit(self::PREVIEW_LIMIT)
                ->get();

            foreach ($requests as $request) {
                $counterpart = $request->isIncomingFor($user)
                    ? $request->requester?->displayName()
                    : ($request->recipient?->displayName() ?? __('ui.user_requests.organizer_flag_subject'));

                $parts = array_values(array_filter([
                    is_string($counterpart) ? trim($counterpart) : '',
                    $labels->resolve($request),
                ], static fn (string $part): bool => $part !== ''));

                $displays[$request->id] = [
                    'title' => $request->type->label(),
                    'subtitle' => $parts === [] ? null : implode(' · ', $parts),
                    'timeAgo' => $request->created_at->diffForHumans(),
                    'needsResponse' => $request->isPending() && $request->isIncomingFor($user),
                ];
            }

            $pendingBadge = $pendingCounter->displayCountFor($user);
        }

        return view('livewire.user-requests.user-request-dropdown', [
            'hasAnyRequests' => $hasAnyRequests,
            'requests' => $requests,
            'displays' => $displays,
            'pendingBadge' => $pendingBadge,
        ]);
    }
}
