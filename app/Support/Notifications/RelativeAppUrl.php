<?php

declare(strict_types=1);

namespace App\Support\Notifications;

final class RelativeAppUrl
{
    public static function normalize(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (str_contains($url, '://')) {
            $appRoot = rtrim((string) config('app.url'), '/');
            if ($appRoot === '' || ! str_starts_with($url, $appRoot.'/')) {
                return null;
            }

            $url = substr($url, strlen($appRoot));
        }

        if (! str_starts_with($url, '/')) {
            $url = '/'.$url;
        }

        if (str_starts_with($url, '//')) {
            return null;
        }

        return $url;
    }
}
