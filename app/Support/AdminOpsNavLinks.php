<?php

declare(strict_types=1);

namespace App\Support;

final class AdminOpsNavLinks
{
    /**
     * Grouped external and in-app ops shortcuts shown in the admin profile menu.
     *
     * @return list<array{label: string|null, items: list<array{label: string, url: string}>}>
     */
    public static function groups(): array
    {
        $groups = [
            [
                'label' => null,
                'items' => self::resolveItems(self::platformItems()),
            ],
            [
                'label' => __('ui.nav.admin_group_environments'),
                'items' => self::resolveItems(self::environmentItems()),
            ],
            [
                'label' => __('ui.nav.admin_group_monitoring'),
                'items' => self::resolveItems(self::monitoringItems()),
            ],
            [
                'label' => __('ui.nav.admin_group_email'),
                'items' => self::resolveItems(self::emailItems()),
            ],
            [
                'label' => __('ui.nav.admin_group_infrastructure'),
                'items' => self::resolveItems(self::infrastructureItems()),
            ],
        ];

        return array_values(array_filter(
            $groups,
            fn (array $group): bool => $group['items'] !== [],
        ));
    }

    /**
     * Flat list of all admin ops links (preserves group order).
     *
     * @return list<array{label: string, url: string}>
     */
    public static function items(): array
    {
        $items = [];

        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private static function platformItems(): array
    {
        return [
            [
                'label' => __('ui.nav.admin_panel'),
                'url' => url('/'.trim((string) config('filament.admin_path'), '/')),
            ],
            [
                'label' => __('ui.nav.pulse'),
                'url' => url('/'.trim((string) config('pulse.path'), '/')),
            ],
        ];
    }

    /**
     * @return list<array{label: string, url: mixed, skip_current_app?: bool}>
     */
    private static function environmentItems(): array
    {
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
        ];
    }

    /**
     * @return list<array{label: string, url: mixed}>
     */
    private static function monitoringItems(): array
    {
        return [
            [
                'label' => __('ui.nav.sentry'),
                'url' => config('sentry.dashboard_url'),
            ],
            [
                'label' => __('ui.nav.umami'),
                'url' => config('umami.dashboard_url'),
            ],
            [
                'label' => __('ui.nav.google_search_console'),
                'url' => config('services.google.search_console_url'),
            ],
        ];
    }

    /**
     * @return list<array{label: string, url: mixed}>
     */
    private static function emailItems(): array
    {
        return [
            [
                'label' => __('ui.nav.support'),
                'url' => config('mail.support_mailbox_url'),
            ],
            [
                'label' => __('ui.nav.brevo'),
                'url' => config('mail.brevo_dashboard_url'),
            ],
        ];
    }

    /**
     * @return list<array{label: string, url: mixed}>
     */
    private static function infrastructureItems(): array
    {
        $adminerBase = config('services.adminer.url');

        return [
            [
                'label' => __('ui.nav.adminer'),
                'url' => filled($adminerBase)
                    ? rtrim((string) $adminerBase, '/').'/?pgsql=pgsql&username=sail'
                    : null,
            ],
            [
                'label' => __('ui.nav.hosting_manager'),
                'url' => config('services.hosting_manager.url'),
            ],
        ];
    }

    /**
     * @param  list<array{label: string, url: mixed, skip_current_app?: bool}>  $optionalItems
     * @return list<array{label: string, url: string}>
     */
    private static function resolveItems(array $optionalItems): array
    {
        $links = [];

        foreach ($optionalItems as $item) {
            $url = $item['url'];

            if (! is_string($url) || $url === '') {
                continue;
            }

            $isEnvironmentSwitch = $item['skip_current_app'] ?? false;

            if ($isEnvironmentSwitch && self::pointsAtCurrentApp($url)) {
                continue;
            }

            if ($isEnvironmentSwitch) {
                $url = self::withCurrentPage($url);
            }

            $links[] = [
                'label' => $item['label'],
                'url' => $url,
            ];
        }

        return $links;
    }

    /**
     * Point an environment origin at the same path (+ query) as the page being viewed.
     */
    private static function withCurrentPage(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return $baseUrl;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $pathAndQuery = self::currentPathAndQuery();

        return $scheme.'://'.$host.$port.$pathAndQuery;
    }

    private static function currentPathAndQuery(): string
    {
        $uri = request()->getRequestUri();

        if (! self::isLivewireUri($uri)) {
            return $uri === '' ? '/' : $uri;
        }

        $referer = request()->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return '/';
        }

        $parts = parse_url($referer);

        if (! is_array($parts) || strcasecmp((string) ($parts['host'] ?? ''), request()->getHost()) !== 0) {
            return '/';
        }

        if (isset($parts['port']) && (int) $parts['port'] !== (int) request()->getPort()) {
            return '/';
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return ($path === '' ? '/' : $path).$query;
    }

    private static function isLivewireUri(string $uri): bool
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = $uri;
        }

        return preg_match('#^/livewire(?:/|-)#', $path) === 1;
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
