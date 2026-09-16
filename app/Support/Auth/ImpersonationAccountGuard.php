<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Http\RedirectResponse;
use STS\FilamentImpersonate\Facades\Impersonation;

/**
 * Blocks sensitive account mutations while an admin is impersonating another user.
 */
final class ImpersonationAccountGuard
{
    public static function blocksMutations(): bool
    {
        return Impersonation::isImpersonating();
    }

    public static function deniedRedirect(string $route = 'dashboard'): RedirectResponse
    {
        session()->flash('ui.toast', [
            'type' => 'error',
            'title' => __('ui.impersonation.account_mutation_blocked'),
        ]);

        return redirect()->route($route);
    }

    /**
     * @return array{
     *     toast: array{
     *         type: string,
     *         title: string,
     *         description: string,
     *         icon: string,
     *         css: string,
     *         timeout: int,
     *         noProgress: bool
     *     }
     * }
     */
    public static function deniedToastPayload(): array
    {
        return [
            'toast' => [
                'type' => 'error',
                'title' => __('ui.impersonation.account_mutation_blocked'),
                'description' => '',
                'icon' => '',
                'css' => 'alert-error',
                'timeout' => 4000,
                'noProgress' => false,
            ],
        ];
    }
}
