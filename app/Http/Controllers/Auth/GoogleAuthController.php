<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Avatars\RefreshCachedAvatar;
use App\Enums\AvatarSource;
use App\Http\Controllers\Auth\Concerns\PersistsOAuthLinkIntent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController extends Controller
{
    use PersistsOAuthLinkIntent;

    public function redirect(): SymfonyRedirectResponse
    {
        $this->captureOAuthLinkIntent();
        $this->captureBrowserTimezoneFromRequest();

        request()->session()->save();

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        if ($this->shouldCompleteAccountLinking()) {
            return $this->completeAccountLinking($googleUser);
        }

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
                $this->syncGoogleAvatarUrl($profile, $this->resolveGoogleAvatarUrl($googleUser));
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
                $this->syncGoogleAvatarUrl($profile, $this->resolveGoogleAvatarUrl($googleUser));
                if ($browserTimezone !== null) {
                    $profile->timezone = $browserTimezone;
                }
                $profile->save();
                $user->setRelation('profile', $profile);
                $this->verifyUserFromGoogleIfApplicable($user, $googleEmailVerified);
            }
        } else {
            $profile = $user->profile()->firstOrCreate();
            $this->syncGoogleAvatarUrl($profile, $this->resolveGoogleAvatarUrl($googleUser));
            $profile->save();
            $user->setRelation('profile', $profile);
        }

        Auth::login($user, true);

        $user->refresh();
        $user->load('profile');
        if ($user->profile?->avatar_source === AvatarSource::Google) {
            try {
                app(RefreshCachedAvatar::class)($user, AvatarSource::Google);
            } catch (\Throwable) {
            }
        }

        $returnTab = session()->pull('socialite.return_tab');
        if (is_string($returnTab) && in_array($returnTab, ['avatar', 'contact'], true)) {
            return redirect()->to($this->profileUrlForTab($returnTab));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function completeAccountLinking(AbstractUser $googleUser): RedirectResponse
    {
        $linkContext = $this->resolveLinkContext();
        $returnTab = $linkContext['returnTab'] ?? 'avatar';
        $profileUrl = $this->profileUrlForTab($returnTab);

        if ($linkContext === null) {
            return redirect()->to($profileUrl);
        }

        $linkUserId = $linkContext['userId'];
        $googleId = (string) $googleUser->getId();
        $avatarUrl = $this->resolveGoogleAvatarUrl($googleUser);
        $hasAvatar = is_string($avatarUrl) && $avatarUrl !== '';

        if ($googleId === '' && ! $hasAvatar) {
            return $this->redirectToProfileTabWithToast(
                $returnTab,
                __('ui.profile.oauth_link_google_failed'),
                'error',
            );
        }

        $user = User::find($linkUserId);
        if ($user === null) {
            return redirect()->route('login')->with(
                'status',
                __('ui.profile.oauth_link_session_expired')
            );
        }

        if ($googleId !== '') {
            $alreadyLinked = User::query()
                ->where('id', '!=', $user->id)
                ->whereHas('profile', fn ($query) => $query->where('google_id', $googleId))
                ->exists();

            if ($alreadyLinked) {
                return $this->redirectToProfileTabWithToast(
                    $returnTab,
                    __('ui.profile.oauth_link_google_taken'),
                    'error',
                );
            }
        }

        $profile = $user->profile()->firstOrCreate();
        if ($googleId !== '') {
            $profile->google_id = $googleId;
        }
        $this->syncGoogleAvatarUrl($profile, $avatarUrl);
        if ($returnTab === 'avatar') {
            $profile->avatar_source = AvatarSource::Google;
        }
        $profile->save();
        $user->setRelation('profile', $profile);

        Auth::login($user, true);

        $user->refresh();
        $user->load('profile');
        if ($user->profile?->avatar_source === AvatarSource::Google) {
            try {
                app(RefreshCachedAvatar::class)($user, AvatarSource::Google);
            } catch (\Throwable) {
            }
        }

        return $this->redirectToProfileTabWithToast($returnTab, __('ui.profile.oauth_link_google_success'));
    }

    private function syncGoogleAvatarUrl(UserProfile $profile, mixed $avatarUrl): void
    {
        if (! is_string($avatarUrl) || $avatarUrl === '') {
            return;
        }

        $profile->google_avatar_url = $avatarUrl;
    }

    private function resolveGoogleAvatarUrl(AbstractUser $googleUser): ?string
    {
        $avatarUrl = $googleUser->getAvatar();
        if (is_string($avatarUrl) && $avatarUrl !== '') {
            return $avatarUrl;
        }

        $raw = $googleUser->user;
        if (! is_array($raw)) {
            return null;
        }

        foreach (['picture', 'avatar', 'image.url', 'photos.0.value'] as $key) {
            $candidate = Arr::get($raw, $key);
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
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
