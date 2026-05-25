<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AvatarSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'google_id',
        'facebook_id',
        'avatar_path',
        'avatar_source',
        'avatar_cache_signature',
        'google_avatar_url',
        'facebook_avatar_url',
        'avatar_bg_color',
        'avatar_text_color',
        'avatar_initials',
        'discord_handle',
        'current_location',
        'timezone',
        'languages',
        'notification_preferences',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'avatar_source' => AvatarSource::class,
            'languages' => 'array',
            'notification_preferences' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
