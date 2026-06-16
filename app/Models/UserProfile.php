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
        'discord_id',
        'avatar_path',
        'avatar_source',
        'avatar_cache_signature',
        'google_avatar_url',
        'google_email',
        'facebook_avatar_url',
        'facebook_email',
        'discord_avatar_url',
        'discord_email',
        'verified_email',
        'show_contact_email',
        'show_contact_facebook',
        'show_contact_google',
        'show_contact_discord',
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
            'show_contact_email' => 'boolean',
            'show_contact_facebook' => 'boolean',
            'show_contact_google' => 'boolean',
            'show_contact_discord' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
