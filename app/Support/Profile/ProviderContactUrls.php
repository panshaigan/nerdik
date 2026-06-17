<?php

declare(strict_types=1);

namespace App\Support\Profile;

use App\Models\UserProfile;
use Illuminate\Support\Arr;

final class ProviderContactUrls
{
    /**
     * @return array{profileUrl: string, messagesUrl: string, messengerUrl: string}|null
     */
    public function facebook(UserProfile $profile): ?array
    {
        if (! filled($profile->facebook_id)) {
            return null;
        }

        $data = is_array($profile->facebook_data) ? $profile->facebook_data : [];

        $profileUrl = $this->stringOrNull(Arr::get($data, 'profile_url'));
        $publicId = $this->stringOrNull(Arr::get($data, 'public_id'));
        $messagesUrl = $this->stringOrNull(Arr::get($data, 'messages_url'));
        $messengerUrl = $this->stringOrNull(Arr::get($data, 'messenger_url'));

        if ($profileUrl === null && $publicId !== null) {
            $profileUrl = 'https://www.facebook.com/profile.php?id='.rawurlencode($publicId);
        }

        if ($profileUrl !== null) {
            $identifier = $publicId ?? $this->stringOrNull(Arr::get($data, 'vanity')) ?? (string) $profile->facebook_id;

            return [
                'profileUrl' => $profileUrl,
                'messagesUrl' => $messagesUrl ?? $this->legacyFacebookMessagesUrl($identifier),
                'messengerUrl' => $messengerUrl ?? $this->legacyFacebookMessengerUrl($identifier),
            ];
        }

        return $this->legacyFacebookUrls((string) $profile->facebook_id);
    }

    /**
     * @return array{webUrl: string, appUrl: string}|null
     */
    public function discord(UserProfile $profile): ?array
    {
        if (! filled($profile->discord_id)) {
            return null;
        }

        $data = is_array($profile->discord_data) ? $profile->discord_data : [];

        $webUrl = $this->stringOrNull(Arr::get($data, 'profile_web_url'));
        $appUrl = $this->stringOrNull(Arr::get($data, 'profile_app_url'));

        if ($webUrl !== null && $appUrl !== null) {
            return [
                'webUrl' => $webUrl,
                'appUrl' => $appUrl,
            ];
        }

        return $this->legacyDiscordUrls((string) $profile->discord_id);
    }

    /**
     * @return array{profileUrl: string, messagesUrl: string, messengerUrl: string}
     */
    private function legacyFacebookUrls(string $facebookId): array
    {
        return [
            'profileUrl' => 'https://www.facebook.com/profile.php?id='.rawurlencode($facebookId),
            'messagesUrl' => $this->legacyFacebookMessagesUrl($facebookId),
            'messengerUrl' => $this->legacyFacebookMessengerUrl($facebookId),
        ];
    }

    private function legacyFacebookMessagesUrl(string $facebookId): string
    {
        return 'https://www.facebook.com/messages/t/'.rawurlencode($facebookId);
    }

    private function legacyFacebookMessengerUrl(string $facebookId): string
    {
        return 'https://m.me/'.rawurlencode($facebookId);
    }

    /**
     * @return array{webUrl: string, appUrl: string}
     */
    private function legacyDiscordUrls(string $discordId): array
    {
        return [
            'webUrl' => 'https://discord.com/users/'.rawurlencode($discordId),
            'appUrl' => 'discord://-/users/'.rawurlencode($discordId),
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
