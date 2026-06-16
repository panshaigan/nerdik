<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Avatars\RefreshCachedAvatar;
use App\Enums\AvatarSource;
use App\Http\Controllers\Auth\Concerns\PersistsOAuthLinkIntent;
use App\Http\Controllers\Auth\Concerns\SyncsProviderEmail;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class DiscordAuthController extends Controller
{
    use PersistsOAuthLinkIntent;
    use SyncsProviderEmail;

    public function redirect(): SymfonyRedirectResponse
    {
        $this->captureOAuthLinkIntent();
        $this->captureBrowserTimezoneFromRequest();

        request()->session()->save();

        return Socialite::driver('discord')->scopes(['identify', 'email'])->redirect();
    }

    public function callback(): RedirectResponse
    {
        $discordUser = Socialite::driver('discord')->user();
        $discordEmail = $discordUser->getEmail();

        if ($this->shouldCompleteAccountLinking()) {
            return $this->completeAccountLinking($discordUser);
        }

        $browserTimezone = $this->resolveBrowserTimezone();

        if ($discordEmail === null || $discordEmail === '') {
            return redirect()->route('login')->with(
                'status',
                __('Discord did not share your email. Please use another method.')
            );
        }

        $user = User::whereHas('profile', function ($query) use ($discordUser): void {
            $query->where('discord_id', $discordUser->getId());
        })->first();

        if (! $user) {
            $user = User::where('email', $discordEmail)->first();
            if ($user) {
                $profile = $user->profile()->firstOrCreate();
                $profile->discord_id = $discordUser->getId();
                $this->syncDiscordAvatarUrl($profile, $discordUser->getAvatar());
                $this->syncDiscordHandle($profile, $discordUser->getNickname());
                $this->syncDiscordProviderEmail($profile, $discordUser);
                if ($profile->timezone === null && $browserTimezone !== null) {
                    $profile->timezone = $browserTimezone;
                }
                $profile->save();
                $user->setRelation('profile', $profile);
                $this->verifyUserFromDiscordIfApplicable($user);
            } else {
                $user = User::create([
                    'name' => $discordUser->getName(),
                    'nickname' => User::generateUniqueNicknameFromEmail($discordEmail),
                    'email' => $discordEmail,
                    'password' => Hash::make(uniqid('', true)),
                ]);
                $profile = $user->profile()->firstOrCreate();
                $profile->discord_id = $discordUser->getId();
                $this->syncDiscordAvatarUrl($profile, $discordUser->getAvatar());
                $this->syncDiscordHandle($profile, $discordUser->getNickname());
                $this->syncDiscordProviderEmail($profile, $discordUser);
                if ($browserTimezone !== null) {
                    $profile->timezone = $browserTimezone;
                }
                $profile->save();
                $user->setRelation('profile', $profile);
                $this->verifyUserFromDiscordIfApplicable($user);
            }
        } else {
            $profile = $user->profile()->firstOrCreate();
            $this->syncDiscordAvatarUrl($profile, $discordUser->getAvatar());
            $this->syncDiscordHandle($profile, $discordUser->getNickname());
            $this->syncDiscordProviderEmail($profile, $discordUser);
            $profile->save();
            $user->setRelation('profile', $profile);
        }

        Auth::login($user, true);

        $user->refresh();
        $user->load('profile');
        if ($user->profile?->avatar_source === AvatarSource::Discord) {
            try {
                app(RefreshCachedAvatar::class)($user, AvatarSource::Discord);
            } catch (\Throwable) {
            }
        }

        $returnTab = session()->pull('socialite.return_tab');
        if (is_string($returnTab) && in_array($returnTab, ['avatar', 'contact'], true)) {
            return redirect()->to($this->profileUrlForTab($returnTab));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function completeAccountLinking(AbstractUser $discordUser): RedirectResponse
    {
        $linkContext = $this->resolveLinkContext();
        $returnTab = $linkContext['returnTab'] ?? 'avatar';
        $profileUrl = $this->profileUrlForTab($returnTab);

        if ($linkContext === null) {
            return redirect()->to($profileUrl);
        }

        $linkUserId = $linkContext['userId'];
        $discordId = (string) $discordUser->getId();
        $hasAvatar = is_string($discordUser->getAvatar()) && $discordUser->getAvatar() !== '';

        if ($discordId === '' && ! $hasAvatar) {
            return $this->redirectToProfileTabWithToast(
                $returnTab,
                __('ui.profile.oauth_link_discord_failed'),
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

        if ($discordId !== '') {
            $alreadyLinked = User::query()
                ->where('id', '!=', $user->id)
                ->whereHas('profile', fn ($query) => $query->where('discord_id', $discordId))
                ->exists();

            if ($alreadyLinked) {
                return $this->redirectToProfileTabWithToast(
                    $returnTab,
                    __('ui.profile.oauth_link_discord_taken'),
                    'error',
                );
            }
        }

        $profile = $user->profile()->firstOrCreate();
        if ($discordId !== '') {
            $profile->discord_id = $discordId;
        }
        $this->syncDiscordAvatarUrl($profile, $discordUser->getAvatar());
        $this->syncDiscordHandle($profile, $discordUser->getNickname());
        $this->syncDiscordProviderEmail($profile, $discordUser);
        if ($returnTab === 'avatar') {
            $profile->avatar_source = AvatarSource::Discord;
        }
        $profile->save();
        $user->setRelation('profile', $profile);

        Auth::login($user, true);

        $user->refresh();
        $user->load('profile');
        if ($user->profile?->avatar_source === AvatarSource::Discord) {
            try {
                app(RefreshCachedAvatar::class)($user, AvatarSource::Discord);
            } catch (\Throwable) {
            }
        }

        return $this->redirectToProfileTabWithToast($returnTab, __('ui.profile.oauth_link_discord_success'));
    }

    private function syncDiscordAvatarUrl(UserProfile $profile, mixed $avatarUrl): void
    {
        if (! is_string($avatarUrl) || $avatarUrl === '') {
            return;
        }

        $profile->discord_avatar_url = $avatarUrl;
    }

    private function syncDiscordHandle(UserProfile $profile, mixed $nickname): void
    {
        if (! is_string($nickname) || $nickname === '') {
            return;
        }

        $profile->discord_handle = $nickname;
    }

    /**
     * Discord only returns an email when the user has confirmed it on Discord,
     * so any non-null email from the OAuth response can be treated as verified.
     */
    private function verifyUserFromDiscordIfApplicable(User $user): void
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
