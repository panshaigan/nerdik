<?php

declare(strict_types=1);

namespace App\Actions\Avatars;

use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

final class ResolveAvatarUrl
{
    public function __invoke(User $user): string
    {
        $profile = $user->profile;
        $name = $user->displayName();

        $bg = ltrim((string) ($profile?->avatar_bg_color ?? '#1d4ed8'), '#');
        $fg = ltrim((string) ($profile?->avatar_text_color ?? '#ffffff'), '#');

        $generated = sprintf(
            'https://ui-avatars.com/api/?name=%s&background=%s&color=%s&rounded=true&bold=true',
            rawurlencode($name),
            rawurlencode($bg),
            rawurlencode($fg),
        );

        $rawSource = $profile?->avatar_source;
        $source = $rawSource instanceof AvatarSource
            ? $rawSource
            : (AvatarSource::tryFrom((string) ($rawSource ?? AvatarSource::Generated->value)) ?? AvatarSource::Generated);

        if ($source === AvatarSource::Generated) {
            return $generated;
        }

        $path = $profile?->avatar_path;
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        if ($source === AvatarSource::Google && is_string($profile?->google_avatar_url) && $profile->google_avatar_url !== '') {
            return $profile->google_avatar_url;
        }

        if ($source === AvatarSource::Facebook && is_string($profile?->facebook_avatar_url) && $profile->facebook_avatar_url !== '') {
            return $profile->facebook_avatar_url;
        }

        if ($source === AvatarSource::Gravatar) {
            $hash = md5(strtolower(trim((string) $user->email)));

            return 'https://www.gravatar.com/avatar/'.$hash.'?s=512&d=mp';
        }

        return $generated;
    }
}
