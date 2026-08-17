<?php

declare(strict_types=1);

namespace App\Actions\Locale;

use App\Enums\AppLocale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SwitchLocale
{
    public function __construct(
        private readonly SyncPreferredLocale $syncPreferredLocale
    ) {}

    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $appLocale = AppLocale::tryFrom($locale);
        abort_unless($appLocale instanceof AppLocale, 404);

        $user = $request->user();
        if ($user instanceof User) {
            $this->syncPreferredLocale->persist($user, $appLocale);
        }

        $this->syncPreferredLocale->applyToRequest($appLocale);

        $redirectTo = (string) $request->query('redirect', '');
        if (! str_starts_with($redirectTo, '/') || str_starts_with($redirectTo, '//')) {
            $redirectTo = route('dashboard');
        }

        return redirect($redirectTo);
    }
}
