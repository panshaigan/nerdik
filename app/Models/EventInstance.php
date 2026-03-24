<?php

namespace App\Models;

use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventInstance extends Model
{
    use HasMetaColumns, SoftDeletes;

    protected $fillable = [
        'event_id',
        'created_by',
        'name',
        'desc',
        'starts_at',
        'ends_at',
        'logo_path',
        'slug',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function slots()
    {
        return $this->hasMany(Slot::class);
    }

    public function proposals()
    {
        return $this->hasMany(ActivityProposal::class, 'event_instance_id');
    }
}
