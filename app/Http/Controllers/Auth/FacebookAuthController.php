<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class FacebookAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('facebook')->scopes(['email'])->redirect();
    }

    public function callback(): RedirectResponse
    {
        $facebookUser = Socialite::driver('facebook')->user();
        $facebookEmail = $facebookUser->getEmail();

        if ($facebookEmail === null || $facebookEmail === '') {
            return redirect()->route('login')->with(
                'status',
                __('Facebook did not share your email. Please use another method.')
            );
        }

        $user = User::whereHas('profile', function ($query) use ($facebookUser): void {
            $query->where('facebook_id', $facebookUser->getId());
        })->first();

        if (! $user) {
            $user = User::where('email', $facebookEmail)->first();
            if ($user) {
                $profile = $user->profile()->firstOrCreate();
                $profile->facebook_id = $facebookUser->getId();
                $profile->save();
                $user->setRelation('profile', $profile);
                $this->verifyUserFromFacebookIfApplicable($user);
            } else {
                $user = User::create([
                    'name' => $facebookUser->getName(),
                    'nickname' => User::generateUniqueNicknameFromEmail($facebookEmail),
                    'email' => $facebookEmail,
                    'password' => Hash::make(uniqid('', true)),
                ]);
                $profile = $user->profile()->firstOrCreate();
                $profile->facebook_id = $facebookUser->getId();
                $profile->save();
                $user->setRelation('profile', $profile);
                $this->verifyUserFromFacebookIfApplicable($user);
            }
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Facebook only returns an email when the user has confirmed it on Facebook,
     * so any non-null email from the OAuth response can be treated as verified.
     */
    private function verifyUserFromFacebookIfApplicable(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }
    }
}
