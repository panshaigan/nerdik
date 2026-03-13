<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventInstance extends Model
{
    protected $fillable = [
        'event_id',
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
}
