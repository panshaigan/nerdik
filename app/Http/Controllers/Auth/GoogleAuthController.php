<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        $browserTimezone = $this->resolveBrowserTimezone();

        $user = User::whereHas('profile', function ($query) use ($googleUser): void {
            $query->where('google_id', $googleUser->getId());
        })->first();

        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())->first();
            if ($user) {
                $profile = $user->profile()->firstOrCreate();
                $profile->google_id = $googleUser->getId();
                if ($profile->timezone === null && $browserTimezone !== null) {
                    $profile->timezone = $browserTimezone;
                }
                $profile->save();
                $user->setRelation('profile', $profile);
                $this->verifyUserFromGoogleIfApplicable($user, $googleEmailVerified);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'nickname' => User::generateUniqueNicknameFromEmail((string) $googleUser->getEmail()),
                    'email' => $googleUser->getEmail(),
                    'password' => Hash::make(uniqid('', true)),
                ]);
                $profile = $user->profile()->firstOrCreate();
                $profile->google_id = $googleUser->getId();
                if ($browserTimezone !== null) {
                    $profile->timezone = $browserTimezone;
                }
                $profile->save();
                $user->setRelation('profile', $profile);
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

    private function resolveBrowserTimezone(): ?string
    {
        $raw = request()->cookie('browser_timezone');
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return in_array($raw, timezone_identifiers_list(), true) ? $raw : null;
    }
}
