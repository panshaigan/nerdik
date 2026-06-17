<?php

declare(strict_types=1);

namespace App\Support\Profile;

use App\Models\UserProfile;
use Illuminate\Support\Arr;

final class ProviderContactUrls
{
    /**
     * @return array{profileUrl: string, messagesUrl: ?string, messengerUrl: ?string}|null
     */
    public function facebook(UserProfile $profile): ?array
    {
        $userProfileUrl = $this->stringOrNull($profile->facebook_profile_url);

        if (! filled($profile->facebook_id) && $userProfileUrl === null) {
            return null;
        }

        $data = is_array($profile->facebook_data) ? $profile->facebook_data : [];

        $profileUrl = $userProfileUrl ?? $this->stringOrNull(Arr::get($data, 'profile_url'));
        $publicId = $this->stringOrNull(Arr::get($data, 'public_id'));
        $messagesUrl = $this->stringOrNull(Arr::get($data, 'messages_url'));
        $messengerUrl = $this->stringOrNull(Arr::get($data, 'messenger_url'));

        if ($profileUrl === null && $publicId !== null) {
            $profileUrl = 'https://www.facebook.com/profile.php?id='.rawurlencode($publicId);
        }

        if ($profileUrl === null && filled($profile->facebook_id)) {
            return $this->legacyFacebookUrls((string) $profile->facebook_id);
        }

        if ($profileUrl === null) {
            return null;
        }

        $parsed = $this->parseProfileUrl($profileUrl);
        $identifier = $publicId
            ?? $parsed['public_id']
            ?? $parsed['vanity']
            ?? (filled($profile->facebook_id) ? (string) $profile->facebook_id : null);

        return [
            'profileUrl' => $profileUrl,
            'messagesUrl' => $messagesUrl ?? ($identifier !== null ? $this->legacyFacebookMessagesUrl($identifier) : null),
            'messengerUrl' => $messengerUrl ?? ($identifier !== null ? $this->legacyFacebookMessengerUrl($identifier) : null),
        ];
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

    /**
     * @return array{vanity: ?string, public_id: ?string}|null
     */
    private function parseProfileUrl(string $profileUrl): ?array
    {
        $parts = parse_url($profileUrl);
        if (! is_array($parts)) {
            return null;
        }

        $path = $parts['path'] ?? '';
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_contains($path, 'profile.php')) {
            parse_str($parts['query'] ?? '', $query);

            $publicId = $query['id'] ?? null;

            return [
                'vanity' => null,
                'public_id' => is_string($publicId) && $publicId !== '' ? $publicId : null,
            ];
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $vanity = $segments[0] ?? null;

        if (! is_string($vanity) || $vanity === '' || in_array($vanity, ['people', 'pages', 'groups', 'app_scoped_user_id'], true)) {
            return null;
        }

        return [
            'vanity' => $vanity,
            'public_id' => null,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
