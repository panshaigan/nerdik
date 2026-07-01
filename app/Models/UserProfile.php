<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AvatarSource;
use App\Enums\TimeDisplayFormat;
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
        'facebook_profile_url',
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
        'time_display_format',
        'languages',
        'notification_preferences',
        'google_data',
        'facebook_data',
        'discord_data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'avatar_source' => AvatarSource::class,
            'time_display_format' => TimeDisplayFormat::class,
            'languages' => 'array',
            'notification_preferences' => 'array',
            'google_data' => 'array',
            'facebook_data' => 'array',
            'discord_data' => 'array',
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
