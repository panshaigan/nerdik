<?php

namespace App\Livewire\Actions;

use App\Events\SessionInvalidated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use STS\FilamentImpersonate\Facades\Impersonation;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke(): void
    {
        $userId = Auth::guard('web')->id();

        if (Impersonation::isImpersonating()) {
            Impersonation::clear();
        }

        // Broadcast immediately so other open tabs can log out as well.
        if ($userId !== null) {
            SessionInvalidated::dispatch($userId);
        }

        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();
    }
}
