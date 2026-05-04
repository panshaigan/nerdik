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

        return $generated;
    }
}
