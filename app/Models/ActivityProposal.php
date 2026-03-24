<?php

namespace App\Models;

use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityProposal extends Model
{
    use HasMetaColumns, SoftDeletes;

    protected $fillable = [
        'activity_id',
        'event_instance_id',
        'created_by',
        'updated_by',
        'preferred_start_time',
        'status',
        'accepted_slot_id',
    ];

    protected $casts = [
        'preferred_start_time' => 'datetime',
    ];

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function eventInstance()
    {
        return $this->belongsTo(EventInstance::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptedSlot()
    {
        return $this->belongsTo(Slot::class, 'accepted_slot_id');
    }

    public function slots()
    {
        return $this->hasMany(ActivityProposalSlot::class);
    }

    /** Slots the proposer targeted (for accept: pick one of these or any free slot in the instance). */
    public function proposedSlots()
    {
        return $this->belongsToMany(Slot::class, 'activity_proposal_slots', 'activity_proposal_id', 'slot_id');
    }
}
