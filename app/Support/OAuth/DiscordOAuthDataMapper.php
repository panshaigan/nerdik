<?php

declare(strict_types=1);

namespace App\Support\OAuth;

use Illuminate\Support\Arr;
use Laravel\Socialite\AbstractUser;

final class DiscordOAuthDataMapper
{
    /**
     * @return array{
     *     synced_at: string,
     *     username: ?string,
     *     global_name: ?string,
     *     display_name: ?string,
     *     profile_web_url: ?string,
     *     profile_app_url: ?string,
     * }|null
     */
    public function map(AbstractUser $socialiteUser): ?array
    {
        $raw = $socialiteUser->getRaw();
        $discordId = $this->stringOrNull($socialiteUser->getId()) ?? $this->stringOrNull(Arr::get($raw, 'id'));

        if ($discordId === null) {
            return null;
        }

        $username = $this->stringOrNull(Arr::get($raw, 'username')) ?? $this->stringOrNull($socialiteUser->getNickname());
        $globalName = $this->stringOrNull(Arr::get($raw, 'global_name'));
        $displayName = $globalName ?? $username ?? $this->stringOrNull($socialiteUser->getName());

        return [
            'synced_at' => now()->toIso8601String(),
            'username' => $username,
            'global_name' => $globalName,
            'display_name' => $displayName,
            'profile_web_url' => 'https://discord.com/users/'.rawurlencode($discordId),
            'profile_app_url' => 'discord://-/users/'.rawurlencode($discordId),
        ];
    }

    /**
     * @return array{handle: ?string}
     */
    public function resolveHandle(AbstractUser $socialiteUser): array
    {
        $mapped = $this->map($socialiteUser);

        return [
            'handle' => $mapped['display_name'] ?? $mapped['username'] ?? null,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
