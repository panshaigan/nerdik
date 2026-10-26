<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserHostBanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHostBan extends Model
{
    /** @use HasFactory<UserHostBanFactory> */
    use HasFactory;

    protected $fillable = [
        'host_user_id',
        'banned_user_id',
    ];

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function bannedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_user_id');
    }
}
