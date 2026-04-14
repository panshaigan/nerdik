<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'slug',
    ];

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function slots()
    {
        return $this->belongsToMany(Slot::class, 'activity_type_slot');
    }
}
