<?php

namespace App\Livewire\ActivityProposals;

use App\Models\ActivityProposal;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProposalIndex extends Component
{
    public function render(): View
    {
        $proposals = ActivityProposal::with(['activity', 'event', 'creator', 'acceptedSlot'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.activity-proposals.proposal-index', [
            'proposals' => $proposals,
        ]);
    }
}
