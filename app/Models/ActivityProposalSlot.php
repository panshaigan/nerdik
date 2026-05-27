<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityProposalSlot extends Model
{
    protected $table = 'activity_proposal_slot';

    public $timestamps = false;

    protected $fillable = [
        'activity_proposal_id',
        'slot_id',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ActivityProposal::class, 'activity_proposal_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }
}
