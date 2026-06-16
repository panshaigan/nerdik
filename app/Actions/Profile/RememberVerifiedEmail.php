<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use App\Models\UserProfile;

final class RememberVerifiedEmail
{
    public function __invoke(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            return;
        }

        $profile = $user->profile()->firstOrCreate();
        $currentEmail = strtolower($user->email);

        if ($this->isLinkedProviderEmail($profile, $currentEmail)) {
            return;
        }

        $profile->verified_email = $currentEmail;
        $profile->save();
        $user->setRelation('profile', $profile);
    }

    private function isLinkedProviderEmail(UserProfile $profile, string $email): bool
    {
        $providerEmails = [
            ['linked' => filled($profile->google_id), 'email' => $profile->google_email],
            ['linked' => filled($profile->facebook_id), 'email' => $profile->facebook_email],
            ['linked' => filled($profile->discord_id), 'email' => $profile->discord_email],
        ];

        foreach ($providerEmails as $provider) {
            if (! $provider['linked']) {
                continue;
            }

            $providerEmail = is_string($provider['email']) ? strtolower($provider['email']) : '';
            if ($providerEmail !== '' && $providerEmail === $email) {
                return true;
            }
        }

        return false;
    }
}
