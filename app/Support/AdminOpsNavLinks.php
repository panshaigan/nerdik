<?php

declare(strict_types=1);

namespace App\Support;

final class AdminOpsNavLinks
{
    /**
     * External and in-app ops shortcuts shown in the admin profile menu.
     *
     * @return list<array{label: string, url: string}>
     */
    public static function items(): array
    {
        $links = [
            [
                'label' => __('ui.nav.admin_panel'),
                'url' => url('/'.trim((string) config('filament.admin_path'), '/')),
            ],
            [
                'label' => __('ui.nav.pulse'),
                'url' => url('/'.trim((string) config('pulse.path'), '/')),
            ],
        ];

        foreach (self::optionalItems() as $item) {
            $url = $item['url'];

            if (! is_string($url) || $url === '') {
                continue;
            }

            if (($item['skip_current_app'] ?? false) && self::pointsAtCurrentApp($url)) {
                continue;
            }

            $links[] = [
                'label' => $item['label'],
                'url' => $url,
            ];
        }

        return $links;
    }

    /**
     * @return list<array{label: string, url: mixed, skip_current_app?: bool}>
     */
    private static function optionalItems(): array
    {
        $adminerBase = config('services.adminer.url');

        return [
            [
                'label' => __('ui.nav.production'),
                'url' => config('app.environment_urls.production'),
                'skip_current_app' => true,
            ],
            [
                'label' => __('ui.nav.staging'),
                'url' => config('app.environment_urls.staging'),
                'skip_current_app' => true,
            ],
            [
                'label' => __('ui.nav.development'),
                'url' => config('app.environment_urls.development'),
                'skip_current_app' => true,
            ],
            [
                'label' => __('ui.nav.sentry'),
                'url' => config('sentry.dashboard_url'),
            ],
            [
                'label' => __('ui.nav.support'),
                'url' => config('mail.support_mailbox_url'),
            ],
            [
                'label' => __('ui.nav.umami'),
                'url' => config('umami.dashboard_url'),
            ],
            [
                'label' => __('ui.nav.google_search_console'),
                'url' => config('services.google.search_console_url'),
            ],
            [
                'label' => __('ui.nav.brevo'),
                'url' => config('mail.brevo_dashboard_url'),
            ],
            [
                'label' => __('ui.nav.adminer'),
                'url' => filled($adminerBase)
                    ? rtrim((string) $adminerBase, '/').'/?pgsql=pgsql&username=sail'
                    : null,
            ],
        ];
    }

    private static function pointsAtCurrentApp(string $url): bool
    {
        $target = parse_url($url);
        $current = parse_url((string) config('app.url'));

        if (! is_array($target) || ! is_array($current)) {
            return false;
        }

        $targetHost = strtolower((string) ($target['host'] ?? ''));
        $currentHost = strtolower((string) ($current['host'] ?? ''));

        if ($targetHost === '' || $currentHost === '' || $targetHost !== $currentHost) {
            return false;
        }

        $targetPort = $target['port'] ?? self::defaultPort($target['scheme'] ?? null);
        $currentPort = $current['port'] ?? self::defaultPort($current['scheme'] ?? null);

        return $targetPort === $currentPort;
    }

    private static function defaultPort(?string $scheme): int
    {
        return strtolower((string) $scheme) === 'https' ? 443 : 80;
    }
}
