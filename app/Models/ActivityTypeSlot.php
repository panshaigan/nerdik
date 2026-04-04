<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityTypeSlot extends Model
{
    protected $table = 'activity_type_slot';

    protected $fillable = [
        'slot_id',
        'activity_type',
    ];

    public $timestamps = true;

    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }
}
