<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Locale\SyncPreferredLocale;
use App\Models\User;
use Illuminate\Auth\Events\Login;

final class SyncPreferredLocaleOnLogin
{
    public function __construct(
        private readonly SyncPreferredLocale $syncPreferredLocale
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $this->syncPreferredLocale->onLogin($user);
    }
}
