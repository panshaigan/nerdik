<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Actions\Avatars\AttachUserAvatarFromPath;
use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AnonymizeUser
{
    /**
     * Neutralise a user's private data while keeping the record for referential
     * integrity. The account is flagged as deleted and can no longer be used.
     */
    public function __invoke(User $user): User
    {
        DB::transaction(function () use ($user): void {
            $profile = $user->profile;

            if ($profile !== null) {
                $profile->forceFill([
                    'google_id' => null,
                    'facebook_id' => null,
                    'discord_id' => null,
                    'google_avatar_url' => null,
                    'facebook_avatar_url' => null,
                    'discord_avatar_url' => null,
                    'google_email' => null,
                    'facebook_email' => null,
                    'discord_email' => null,
                    'google_data' => null,
                    'facebook_data' => null,
                    'discord_data' => null,
                    'verified_email' => null,
                    'avatar_path' => null,
                    'avatar_cache_signature' => null,
                    'avatar_source' => AvatarSource::Generated,
                    'avatar_bg_color' => null,
                    'avatar_text_color' => null,
                    'avatar_initials' => null,
                    'discord_handle' => null,
                    'current_location' => null,
                    'languages' => null,
                    'notification_preferences' => null,
                    'show_contact_email' => false,
                    'show_contact_facebook' => false,
                    'show_contact_google' => false,
                    'show_contact_discord' => false,
                ])->save();
            }

            $user->clearMediaCollection('avatar');
            AttachUserAvatarFromPath::deleteLegacyAvatarFile($user);

            $user->forceFill([
                'name' => null,
                'email' => 'deleted+'.$user->id.'@deleted.invalid',
                'pending_email' => null,
                'email_verified_at' => null,
                'password' => bcrypt(Str::random(64)),
                'remember_token' => null,
                'is_admin' => false,
                'is_event_organizer' => false,
                'is_deleted' => true,
            ])->save();
        });

        return $user->refresh();
    }
}
