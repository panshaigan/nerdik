<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\UserProfile;
use Illuminate\Support\Arr;
use Laravel\Socialite\AbstractUser;

trait SyncsProviderEmail
{
    protected function syncGoogleProviderEmail(UserProfile $profile, AbstractUser $googleUser): void
    {
        if (! $this->isGoogleEmailMarkedVerified($googleUser)) {
            $profile->google_email = null;

            return;
        }

        $email = $googleUser->getEmail();
        if (! is_string($email) || $email === '') {
            $profile->google_email = null;

            return;
        }

        $profile->google_email = strtolower($email);
    }

    protected function syncFacebookProviderEmail(UserProfile $profile, AbstractUser $facebookUser): void
    {
        $email = $facebookUser->getEmail();
        if (! is_string($email) || $email === '') {
            $profile->facebook_email = null;

            return;
        }

        $profile->facebook_email = strtolower($email);
    }

    protected function syncDiscordProviderEmail(UserProfile $profile, AbstractUser $discordUser): void
    {
        $email = $this->isDiscordEmailMarkedVerified($discordUser) ? $discordUser->getEmail() : null;
        if (! is_string($email) || $email === '') {
            $profile->discord_email = null;

            return;
        }

        $profile->discord_email = strtolower($email);
    }

    protected function isGoogleEmailMarkedVerified(AbstractUser $googleUser): bool
    {
        $user = $googleUser->user;

        if (! is_array($user)) {
            return false;
        }

        return Arr::get($user, 'verified_email') === true;
    }

    protected function isDiscordEmailMarkedVerified(AbstractUser $discordUser): bool
    {
        return is_array($discordUser->user) && Arr::get($discordUser->user, 'verified') === true;
    }
}
