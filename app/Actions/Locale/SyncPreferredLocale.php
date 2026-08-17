<?php

declare(strict_types=1);

namespace App\Actions\Locale;

use App\Enums\AppLocale;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

final class SyncPreferredLocale
{
    public const COOKIE_MINUTES = 60 * 24 * 365;

    public function persist(User $user, AppLocale $locale): void
    {
        $user->forceFill(['locale' => $locale])->save();
    }

    public function applyToRequest(AppLocale $locale): void
    {
        session(['locale' => $locale->value]);
        Cookie::queue('locale', $locale->value, self::COOKIE_MINUTES);
        App::setLocale($locale->value);
    }

    public function current(): AppLocale
    {
        $fromSession = session('locale');
        if (AppLocale::isSupported(is_string($fromSession) ? $fromSession : null)) {
            return AppLocale::from($fromSession);
        }

        return AppLocale::coerce(App::getLocale());
    }

    public function onLogin(User $user): void
    {
        if ($user->locale === null) {
            $this->persist($user, $this->current());
        }

        $this->applyToRequest(AppLocale::coerce($user->preferredLocale()));
    }
}
