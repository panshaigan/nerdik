<?php

declare(strict_types=1);

namespace App\Support\Seo;

final class RobotsTxt
{
    public function body(): string
    {
        $lines = [
            'User-agent: *',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /auth/',
            'Disallow: /verify-email',
            'Disallow: /confirm-password',
            '',
            'User-agent: Amazonbot',
            'Disallow: /',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ];

        return implode("\n", $lines);
    }
}
