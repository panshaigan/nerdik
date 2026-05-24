<?php

declare(strict_types=1);

namespace App\Support\Ui;

final class ManageFormBackUrl
{
    /**
     * Resolve the back link for activity/event manage forms.
     *
     * @param  string|null  $editShowRoute  Show-page route when editing; null on create.
     */
    public static function resolve(?string $editShowRoute): string
    {
        foreach ([
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
}
