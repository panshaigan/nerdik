<?php

declare(strict_types=1);

namespace App\Support\OAuth;

use Illuminate\Support\Arr;
use Laravel\Socialite\AbstractUser;

final class GoogleOAuthDataMapper
{
    /**
     * @return array{
     *     synced_at: string,
     *     given_name: ?string,
     *     family_name: ?string,
     *     locale: ?string,
     * }|null
     */
    public function map(AbstractUser $socialiteUser): ?array
    {
        $raw = $socialiteUser->getRaw();
        if (! is_array($raw)) {
            return null;
        }

        $givenName = $this->stringOrNull(Arr::get($raw, 'given_name'));
        $familyName = $this->stringOrNull(Arr::get($raw, 'family_name'));
        $locale = $this->stringOrNull(Arr::get($raw, 'locale'));

        if ($givenName === null && $familyName === null && $locale === null) {
            return null;
        }

        return [
            'synced_at' => now()->toIso8601String(),
            'given_name' => $givenName,
            'family_name' => $familyName,
            'locale' => $locale,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
