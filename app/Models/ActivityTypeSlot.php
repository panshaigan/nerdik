<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityTypeSlot extends Model
{
    protected $table = 'activity_type_slot';

    protected $fillable = [
        'slot_id',
        'activity_type_id',
    ];

    public $timestamps = false;

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }
}
