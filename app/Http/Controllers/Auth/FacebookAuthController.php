<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Avatars\RefreshCachedAvatar;
use App\Enums\AvatarSource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class FacebookAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        if (request()->query('return_tab') === 'avatar') {
            session(['socialite.return_tab' => 'avatar']);
        }

        $this->captureBrowserTimezoneFromRequest();

        return Socialite::driver('facebook')->scopes(['email'])->redirect();
    }

    public function callback(): RedirectResponse
    {
        $facebookUser = Socialite::driver('facebook')->user();
        $facebookEmail = $facebookUser->getEmail();
        $browserTimezone = $this->resolveBrowserTimezone();

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
                $this->syncFacebookAvatarUrl($profile, $facebookUser->getAvatar());
                if ($profile->timezone === null && $browserTimezone !== null) {
                    $profile->timezone = $browserTimezone;
                }
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
                $this->syncFacebookAvatarUrl($profile, $facebookUser->getAvatar());
                if ($browserTimezone !== null) {
                    $profile->timezone = $browserTimezone;
                }
                $profile->save();
                $user->setRelation('profile', $profile);
                $this->verifyUserFromFacebookIfApplicable($user);
            }
        } else {
            $profile = $user->profile()->firstOrCreate();
            $this->syncFacebookAvatarUrl($profile, $facebookUser->getAvatar());
            $profile->save();
            $user->setRelation('profile', $profile);
        }

        Auth::login($user, true);

        $user->refresh();
        $user->load('profile');
        if ($user->profile?->avatar_source === AvatarSource::Facebook) {
            try {
                app(RefreshCachedAvatar::class)($user, AvatarSource::Facebook);
            } catch (\Throwable) {
            }
        }

        if (session()->pull('socialite.return_tab') === 'avatar') {
            return redirect()->to(route('profile', absolute: false).'?tab=avatar');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function syncFacebookAvatarUrl(UserProfile $profile, mixed $avatarUrl): void
    {
        if (! is_string($avatarUrl) || $avatarUrl === '') {
            return;
        }

        $profile->facebook_avatar_url = $avatarUrl;
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

    private function resolveBrowserTimezone(): ?string
    {
        $raw = request()->query('tz');
        if (! is_string($raw) || $raw === '') {
            $raw = request()->cookie('browser_timezone');
        }
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return in_array($raw, timezone_identifiers_list(), true) ? $raw : null;
    }

    private function captureBrowserTimezoneFromRequest(): void
    {
        $timezone = request()->query('tz');
        if (! is_string($timezone) || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return;
        }

        Cookie::queue('browser_timezone', $timezone, 60 * 24 * 365, null, null, false, false, false, 'lax');
    }
}
