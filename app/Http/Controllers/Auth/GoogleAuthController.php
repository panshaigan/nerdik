<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->user();
        $googleEmailVerified = $this->isGoogleEmailMarkedVerified($googleUser);

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())->first();
            if ($user) {
                $user->update(['google_id' => $googleUser->getId()]);
                $this->verifyUserFromGoogleIfApplicable($user, $googleEmailVerified);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'nickname' => Str::slug(explode('@', (string) $googleUser->getEmail())[0], '_'),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'password' => Hash::make(uniqid('', true)),
                ]);
                $this->verifyUserFromGoogleIfApplicable($user, $googleEmailVerified);
            }
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function isGoogleEmailMarkedVerified(AbstractUser $googleUser): bool
    {
        $user = $googleUser->user;

        if (! is_array($user)) {
            return false;
        }

        return (bool) Arr::get($user, 'verified_email', false);
    }

    /**
     * When Google attests the email is verified, mark the local account verified (new or linked users).
     */
    private function verifyUserFromGoogleIfApplicable(User $user, bool $googleEmailVerified): void
    {
        if (! $googleEmailVerified || $user->hasVerifiedEmail()) {
            return;
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }
    }
}
