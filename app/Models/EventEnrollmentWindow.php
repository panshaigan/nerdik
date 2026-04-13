<?php

namespace App\Models;

use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventEnrollmentWindow extends Model
{
    use HasFactory, HasMetaColumns;

    protected $fillable = [
        'event_id',
        'starts_at',
        'ends_at',
        'max_activities_per_user',
        'max_allowed_participants_per_activity',
        'accumulative_activities',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_activities_per_user' => 'integer',
        'max_allowed_participants_per_activity' => 'integer',
        'accumulative_activities' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function maxActivitiesPerUserEffective(): ?int
    {
        $max = $this->max_activities_per_user;

        return ($max === null || $max === 0) ? null : $max;
    }

    public function maxAllowedParticipantsPerActivityEffective(): ?int
    {
        $max = $this->max_allowed_participants_per_activity;

        return ($max === null || $max === 0) ? null : $max;
    }
}
