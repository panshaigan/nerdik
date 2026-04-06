<?php

namespace App\Models;

use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventEnrollmentWindow extends Model
{
    use HasMetaColumns;

    protected $fillable = [
        'event_id',
        'starts_at',
        'ends_at',
        'max_activities_per_user',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_activities_per_user' => 'integer',
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
}
