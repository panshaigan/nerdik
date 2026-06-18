<?php

declare(strict_types=1);

namespace App\Actions\Avatars;

use App\Enums\AvatarSource;
use App\Models\User;
use App\Support\Ui\AvatarSlot;
use Illuminate\Support\Facades\Storage;

final class ResolveAvatarUrl
{
    public function __invoke(User $user, AvatarSlot|int|null $slotOrSize = null): string
    {
        $slot = $slotOrSize instanceof AvatarSlot
            ? $slotOrSize
            : AvatarSlot::fromDisplaySize(is_int($slotOrSize) ? $slotOrSize : null);

        $profile = $user->profile;
        $generated = $user->generatedAvatarUrl($slot->displaySize());

        $rawSource = $profile?->avatar_source;
        $source = $rawSource instanceof AvatarSource
            ? $rawSource
            : (AvatarSource::tryFrom((string) ($rawSource ?? AvatarSource::Generated->value)) ?? AvatarSource::Generated);

        if ($source === AvatarSource::Generated) {
            return $generated;
        }

        if ($user->avatarConversionsPending()) {
            $originalUrl = $user->pendingAvatarOriginalUrl();

            if (is_string($originalUrl) && $originalUrl !== '') {
                return $originalUrl;
            }

            return $generated;
        }

        $media = $user->getFirstMedia('avatar');

        if ($media !== null && $media->hasGeneratedConversion($slot->conversionName())) {
            return $media->getUrl($slot->conversionName());
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

        if ($source === AvatarSource::Discord && is_string($profile?->discord_avatar_url) && $profile->discord_avatar_url !== '') {
            return $profile->discord_avatar_url;
        }

        if ($source === AvatarSource::Gravatar) {
            $hash = md5(strtolower(trim((string) $user->email)));

            return 'https://www.gravatar.com/avatar/'.$hash.'?s='.$slot->displaySize().'&d=mp';
        }

        return $generated;
    }
}
