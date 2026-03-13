<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityProposalSlot extends Model
{
    protected $fillable = [
        'activity_proposal_id',
        'slot_id',
    ];

    public function proposal()
    {
        return $this->belongsTo(ActivityProposal::class, 'activity_proposal_id');
    }

    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }
}
