<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSignupPeriod extends Model
{
    protected $fillable = [
        'event_id',
        'starts_at',
        'ends_at',
        'max_activities',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_activities' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function maxActivitiesEffective(): ?int
    {
        $max = $this->max_activities;

        return ($max === null || $max === 0) ? null : $max;
    }
}
