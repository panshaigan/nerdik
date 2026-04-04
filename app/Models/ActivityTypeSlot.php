<?php

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Model;

class ActivityTypeSlot extends Model
{
    protected $table = 'activity_type_slot';

    protected $fillable = [
        'slot_id',
        'activity_type',
    ];

    protected $casts = [
        'activity_type' => ActivityType::class,
    ];

    public $timestamps = true;

    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }
}
