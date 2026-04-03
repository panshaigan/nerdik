<?php

namespace App\Livewire\ActivityProposals;

use App\Models\ActivityProposal;
use Livewire\Component;

class ProposalIndex extends Component
{
    public function render()
    {
        $proposals = ActivityProposal::with(['activity', 'event', 'creator', 'acceptedSlot'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.activity-proposals.proposal-index', [
            'proposals' => $proposals,
        ]);
    }
}
