<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Avatars\RefreshCachedAvatar;
use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;

final class RefreshUserAvatarCache implements ShouldQueue
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $user->loadMissing('profile');
        $raw = $user->profile?->avatar_source;
        $source = $raw instanceof AvatarSource
            ? $raw
            : (AvatarSource::tryFrom((string) ($raw ?? AvatarSource::Generated->value)) ?? AvatarSource::Generated);

        if (! $source->usesRemoteCache()) {
            return;
        }

        try {
            app(RefreshCachedAvatar::class)($user, $source);
        } catch (\Throwable) {
            // Best-effort refresh; failures should not block login.
        }
    }
}
