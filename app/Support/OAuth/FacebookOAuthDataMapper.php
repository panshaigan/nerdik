<?php

declare(strict_types=1);

namespace App\Support\OAuth;

use Illuminate\Support\Arr;
use Laravel\Socialite\AbstractUser;

final class FacebookOAuthDataMapper
{
    /**
     * @param  array{name: ?string, link: ?string}|null  $graphProfile
     * @return array{
     *     synced_at: string,
     *     name: ?string,
     *     short_name: ?string,
     *     public_id: ?string,
     *     profile_url: ?string,
     *     vanity: ?string,
     *     messenger_url: ?string,
     *     messages_url: ?string,
     * }|null
     */
    public function map(AbstractUser $socialiteUser, ?array $graphProfile = null): ?array
    {
        $profileUrl = $this->resolveProfileUrl($graphProfile);
        $name = $this->resolveName($socialiteUser, $graphProfile);

        if ($profileUrl === null && $name === null) {
            return null;
        }

        $parsed = $profileUrl !== null ? $this->parseProfileUrl($profileUrl) : null;

        return [
            'synced_at' => now()->toIso8601String(),
            'name' => $name,
            'short_name' => $this->stringOrNull(Arr::get($socialiteUser->getRaw(), 'short_name')),
            'public_id' => $parsed['public_id'] ?? null,
            'profile_url' => $profileUrl,
            'vanity' => $parsed['vanity'] ?? null,
            'messenger_url' => $parsed !== null ? $this->buildMessengerUrl($parsed) : null,
            'messages_url' => $parsed !== null ? $this->buildMessagesUrl($parsed) : null,
        ];
    }

    /**
     * @param  array{name: ?string, link: ?string}|null  $graphProfile
     */
    private function resolveProfileUrl(?array $graphProfile): ?string
    {
        if (! is_array($graphProfile)) {
            return null;
        }

        return $this->stringOrNull($graphProfile['link'] ?? null);
    }

    /**
     * @param  array{name: ?string, link: ?string}|null  $graphProfile
     */
    private function resolveName(AbstractUser $socialiteUser, ?array $graphProfile): ?string
    {
        if (is_array($graphProfile)) {
            $graphName = $this->stringOrNull($graphProfile['name'] ?? null);
            if ($graphName !== null) {
                return $graphName;
            }
        }

        return $this->stringOrNull($socialiteUser->getName())
            ?? $this->stringOrNull(Arr::get($socialiteUser->getRaw(), 'name'));
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

    /**
     * @param  array{vanity: ?string, public_id: ?string}  $parsed
     */
    private function buildMessengerUrl(array $parsed): ?string
    {
        $identifier = $parsed['vanity'] ?? $parsed['public_id'];

        if (! is_string($identifier) || $identifier === '') {
            return null;
        }

        return 'https://m.me/'.rawurlencode($identifier);
    }

    /**
     * @param  array{vanity: ?string, public_id: ?string}  $parsed
     */
    private function buildMessagesUrl(array $parsed): ?string
    {
        $identifier = $parsed['vanity'] ?? $parsed['public_id'];

        if (! is_string($identifier) || $identifier === '') {
            return null;
        }

        return 'https://www.facebook.com/messages/t/'.rawurlencode($identifier);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
