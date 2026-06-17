<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
final class UserProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'google_id' => null,
            'facebook_id' => null,
            'discord_id' => null,
            'avatar_path' => null,
            'avatar_source' => 'generated',
            'avatar_cache_signature' => null,
            'google_avatar_url' => null,
            'google_email' => null,
            'facebook_avatar_url' => null,
            'facebook_email' => null,
            'facebook_profile_url' => null,
            'discord_avatar_url' => null,
            'discord_email' => null,
            'show_contact_email' => false,
            'show_contact_facebook' => true,
            'show_contact_google' => true,
            'show_contact_discord' => true,
            'avatar_bg_color' => '#1d4ed8',
            'avatar_text_color' => '#ffffff',
            'avatar_initials' => null,
            'discord_handle' => null,
            'current_location' => null,
            'timezone' => 'Europe/Warsaw',
            'languages' => null,
            'notification_preferences' => null,
        ];
    }
}
