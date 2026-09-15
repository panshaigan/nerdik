<?php

namespace App\Http\Controllers\Auth\Concerns;

use Illuminate\Http\RedirectResponse;

trait HandlesOAuthCallbackFailures
{
    private function oauthCallbackDeniedOrInvalid(): ?RedirectResponse
    {
        if (request()->filled('error')) {
            return redirect()->route('login')->with(
                'status',
                __('ui.auth.oauth_denied'),
            );
        }

        return null;
    }

    private function oauthInvalidStateRedirect(): RedirectResponse
    {
        return redirect()->route('login')->with(
            'status',
            __('ui.auth.oauth_state_invalid'),
        );
    }
}
