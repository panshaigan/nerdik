<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityWaitlistEntry extends Model
{
    protected $fillable = [
        'activity_id',
        'user_id',
        'position',
    ];

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
