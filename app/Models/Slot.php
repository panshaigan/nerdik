<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slot extends Model
{
    protected $fillable = [
        'event_instance_id',
        'name',
        'starts_at',
        'ends_at',
        'place_id',
        'requires_approval',
        'activity_id',
        'max_capacity',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'requires_approval' => 'boolean',
    ];

    public function eventInstance()
    {
        return $this->belongsTo(EventInstance::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
