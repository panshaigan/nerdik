<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotActivityType extends Model
{
    protected $table = 'slot_activity_type';

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
