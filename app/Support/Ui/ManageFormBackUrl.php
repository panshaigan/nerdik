<?php

declare(strict_types=1);

namespace App\Support\Ui;

final class ManageFormBackUrl
{
    public const SESSION_KEY = 'manage_form.return';

    /**
     * Persist a valid `return` query param for the duration of this manage-form visit.
     */
    public static function captureFromRequest(): void
    {
        $return = safe_return_url(request()->query('return'));

        if ($return !== null) {
            session([self::SESSION_KEY => $return]);

            return;
        }

        session()->forget(self::SESSION_KEY);
    }

    /**
     * Resolve the back link for activity/event manage forms.
     *
     * @param  string|null  $editShowRoute  Show-page route when editing; null on create.
     */
    public static function resolve(?string $editShowRoute): string
    {
        foreach ([
            session(self::SESSION_KEY),
            request()->query('return'),
            session('browsing.return'),
        ] as $candidate) {
            if (is_string($candidate) && ($url = safe_return_url($candidate)) !== null) {
                return $url;
            }
        }

        if ($editShowRoute !== null) {
            return $editShowRoute;
        }

        return route('search.index');
    }

    public static function storedReturnUrl(): ?string
    {
        $stored = session(self::SESSION_KEY);

        return is_string($stored) ? safe_return_url($stored) : null;
    }
}
