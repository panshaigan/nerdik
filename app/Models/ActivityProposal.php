<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityProposal extends Model
{
    protected $fillable = [
        'activity_id',
        'event_instance_id',
        'created_by',
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
}
