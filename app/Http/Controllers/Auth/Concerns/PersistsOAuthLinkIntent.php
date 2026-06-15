<?php

namespace App\Http\Controllers\Auth\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

trait PersistsOAuthLinkIntent
{
    private const string OAUTH_LINK_USER_COOKIE = 'oauth_link_user_id';

    private const int OAUTH_LINK_USER_COOKIE_MINUTES = 15;

    private function captureAvatarLinkIntent(): void
    {
        if (request()->query('return_tab') !== 'avatar') {
            return;
        }

        session(['socialite.return_tab' => 'avatar']);

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

    private function resolveLinkUserId(): ?int
    {
        $linkUserId = session()->pull('socialite.link_user_id') ?? request()->cookie(self::OAUTH_LINK_USER_COOKIE);
        Cookie::queue(Cookie::forget(self::OAUTH_LINK_USER_COOKIE));
        session()->pull('socialite.return_tab');

        if ($linkUserId === null || $linkUserId === '') {
            return null;
        }

        return (int) $linkUserId;
    }

    private function profileAvatarUrl(): string
    {
        return route('profile', absolute: false).'?tab=avatar';
    }

    private function redirectToProfileAvatarWithToast(string $title, string $type = 'success'): RedirectResponse
    {
        session()->flash('ui.toast', [
            'type' => $type,
            'title' => $title,
        ]);

        return redirect()->to($this->profileAvatarUrl());
    }
}
