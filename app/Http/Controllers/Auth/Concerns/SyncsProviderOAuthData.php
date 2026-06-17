<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\UserProfile;
use App\Support\OAuth\DiscordOAuthDataMapper;
use App\Support\OAuth\FacebookGraphProfileFetcher;
use App\Support\OAuth\FacebookOAuthDataMapper;
use App\Support\OAuth\GoogleOAuthDataMapper;
use Laravel\Socialite\AbstractUser;

trait SyncsProviderOAuthData
{
    private function syncGoogleOAuthData(UserProfile $profile, AbstractUser $googleUser): void
    {
        $mapped = app(GoogleOAuthDataMapper::class)->map($googleUser);

        if ($mapped !== null) {
            $profile->google_data = $mapped;
        }
    }

    private function syncFacebookOAuthData(UserProfile $profile, AbstractUser $facebookUser): void
    {
        $token = is_string($facebookUser->token ?? null) ? $facebookUser->token : '';
        $graphProfile = $token !== ''
            ? app(FacebookGraphProfileFetcher::class)->fetchMe($token)
            : null;

        $mapped = app(FacebookOAuthDataMapper::class)->map($facebookUser, $graphProfile);

        if ($mapped !== null) {
            $profile->facebook_data = $mapped;
        }
    }

    private function syncDiscordOAuthData(UserProfile $profile, AbstractUser $discordUser): void
    {
        $mapper = app(DiscordOAuthDataMapper::class);
        $mapped = $mapper->map($discordUser);

        if ($mapped !== null) {
            $profile->discord_data = $mapped;
        }

        $handle = $mapper->resolveHandle($discordUser)['handle'];
        if (is_string($handle) && $handle !== '') {
            $profile->discord_handle = $handle;
        }
    }
}
