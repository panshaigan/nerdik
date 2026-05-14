<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityUser extends Model
{
    protected $table = 'activity_user';

    protected $fillable = [
        'activity_id',
        'user_id',
        'is_absent',
        'created_by',
        'updated_by',
        'deleted_at',
        'deleted_by',
    ];

    protected $casts = [
        'is_absent' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
