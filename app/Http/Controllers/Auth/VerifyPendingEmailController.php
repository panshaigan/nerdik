<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Profile\RememberVerifiedEmail;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPendingEmailController extends Controller
{
    /**
     * Confirm a pending email change for the authenticated user.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (! hash_equals((string) $request->route('id'), (string) $user->getKey())) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (! $user->hasPendingEmailChange()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (! hash_equals((string) $request->route('hash'), sha1((string) $user->pending_email))) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $newEmail = (string) $user->pending_email;

        app(RememberVerifiedEmail::class)($user);

        $user->forceFill([
            'email' => $newEmail,
            'pending_email' => null,
            'email_verified_at' => $user->freshTimestamp(),
        ])->save();

        event(new Verified($user));

        return redirect()
            ->route('profile', ['tab' => 'advanced'])
            ->with('ui.toast', [
                'type' => 'success',
                'title' => __('ui.profile.email_changed_success'),
            ]);
    }
}
