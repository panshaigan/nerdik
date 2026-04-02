<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotPlace extends Model
{
    protected $table = 'slot_place';

    protected $fillable = [
        'slot_id',
        'place_id',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
