<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityUser extends Model
{
    protected $table = 'activity_user';

    protected $fillable = [
        'activity_id',
        'user_id',
        'is_absent',
    ];

    protected $casts = [
        'is_absent' => 'boolean',
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
