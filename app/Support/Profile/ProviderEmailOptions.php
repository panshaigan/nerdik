<?php

declare(strict_types=1);

namespace App\Support\Profile;

use App\Models\User;

final class ProviderEmailOptions
{
    /**
     * @return list<array{id: string, name: string}>
     */
    public static function for(User $user): array
    {
        $profile = $user->profile;
        if ($profile === null) {
            return [];
        }

        $currentEmail = strtolower($user->email);
        $seen = [];
        $options = [];

        $providerEmails = [
            ['linked' => filled($profile->google_id), 'email' => $profile->google_email],
            ['linked' => filled($profile->facebook_id), 'email' => $profile->facebook_email],
            ['linked' => filled($profile->discord_id), 'email' => $profile->discord_email],
        ];

        foreach ($providerEmails as $provider) {
            if (! $provider['linked']) {
                continue;
            }

            $email = is_string($provider['email']) ? strtolower($provider['email']) : '';
            if ($email === '' || $email === $currentEmail || isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;
            $options[] = [
                'id' => $email,
                'name' => $email,
            ];
        }

        $verifiedEmail = is_string($profile->verified_email) ? strtolower($profile->verified_email) : '';
        if ($verifiedEmail !== '' && $verifiedEmail !== $currentEmail && ! isset($seen[$verifiedEmail])) {
            $options[] = [
                'id' => $verifiedEmail,
                'name' => $verifiedEmail,
            ];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function availableEmails(User $user): array
    {
        return array_column(self::for($user), 'id');
    }
}
