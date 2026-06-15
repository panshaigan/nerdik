<?php

namespace App\Http\Controllers\Auth\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

trait PersistsOAuthLinkIntent
{
    private const string OAUTH_LINK_USER_COOKIE = 'oauth_link_user_id';

    private const int OAUTH_LINK_USER_COOKIE_MINUTES = 15;

    /** @var list<string> */
    private const array OAUTH_RETURN_TABS = ['avatar', 'contact'];

    private function captureOAuthLinkIntent(): void
    {
        $returnTab = request()->query('return_tab');
        if (! is_string($returnTab) || ! in_array($returnTab, self::OAUTH_RETURN_TABS, true)) {
            return;
        }

        session(['socialite.return_tab' => $returnTab]);

        if (! Auth::check()) {
            return;
        }

        $userId = Auth::id();
        session(['socialite.link_user_id' => $userId]);
        Cookie::queue(self::OAUTH_LINK_USER_COOKIE, (string) $userId, self::OAUTH_LINK_USER_COOKIE_MINUTES);
    }

    private function shouldCompleteAccountLinking(): bool
    {
        return session()->has('socialite.link_user_id')
            || request()->hasCookie(self::OAUTH_LINK_USER_COOKIE);
    }

    /**
     * @return array{userId: int, returnTab: string}|null
     */
    private function resolveLinkContext(): ?array
    {
        $linkUserId = session()->pull('socialite.link_user_id') ?? request()->cookie(self::OAUTH_LINK_USER_COOKIE);
        $returnTab = session()->pull('socialite.return_tab');
        Cookie::queue(Cookie::forget(self::OAUTH_LINK_USER_COOKIE));

        if ($linkUserId === null || $linkUserId === '') {
            return null;
        }

        if (! is_string($returnTab) || ! in_array($returnTab, self::OAUTH_RETURN_TABS, true)) {
            $returnTab = 'avatar';
        }

        return [
            'userId' => (int) $linkUserId,
            'returnTab' => $returnTab,
        ];
    }

    private function profileUrlForTab(string $tab): string
    {
        if (! in_array($tab, self::OAUTH_RETURN_TABS, true)) {
            $tab = 'avatar';
        }

        return route('profile', absolute: false).'?tab='.$tab;
    }

    private function profileAvatarUrl(): string
    {
        return $this->profileUrlForTab('avatar');
    }

    private function redirectToProfileTabWithToast(string $tab, string $title, string $type = 'success'): RedirectResponse
    {
        session()->flash('ui.toast', [
            'type' => $type,
            'title' => $title,
        ]);

        return redirect()->to($this->profileUrlForTab($tab));
    }

    private function redirectToProfileAvatarWithToast(string $title, string $type = 'success'): RedirectResponse
    {
        return $this->redirectToProfileTabWithToast('avatar', $title, $type);
    }
}
