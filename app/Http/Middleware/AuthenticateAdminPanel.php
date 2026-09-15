<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament panel auth that returns 404 for guests and non-admins.
 *
 * Laravel middleware priority runs Authenticate ahead of later panel middleware,
 * so denial must happen here to avoid leaking the panel via 302 login redirects or 403s.
 */
class AuthenticateAdminPanel extends FilamentAuthenticate
{
    /**
     * @param  array<string>  $guards
     */
    #[\Override]
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            abort(404);
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();

        $panel = Filament::getCurrentOrDefaultPanel();

        abort_if(
            $user instanceof FilamentUser
                ? (! $user->canAccessPanel($panel))
                : (! app()->isLocal()),
            404,
        );
    }
}
