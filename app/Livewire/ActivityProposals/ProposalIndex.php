<?php

namespace App\Livewire\ActivityProposals;

use App\Models\ActivityProposal;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProposalIndex extends Component
{
    public function render()
    {
        $userId = Auth::id();
        abort_unless($userId !== null, 403);

        $proposals = ActivityProposal::query()
            ->where(function ($query) use ($userId): void {
                $query->where('created_by', $userId)
                    ->orWhereHas('event', fn ($eventQuery) => $eventQuery->where('created_by', $userId));
            })
            ->with(['activity', 'event', 'creator', 'acceptedSlot'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.activity-proposals.proposal-index', [
            'proposals' => $proposals,
        ]);
    }
}
