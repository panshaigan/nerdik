<?php

namespace App\Models;

use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Slot extends Model
{
    use HasMetaColumns, SoftDeletes;

    protected $fillable = [
        'event_instance_id',
        'created_by',
        'name',
        'starts_at',
        'ends_at',
        'place_id',
        'requires_approval',
        'activity_id',
        'max_capacity',
        'updated_by',
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
