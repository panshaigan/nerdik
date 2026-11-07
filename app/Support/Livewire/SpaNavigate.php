<?php

declare(strict_types=1);

namespace App\Support\Livewire;

/**
 * Decides whether a same-origin redirect may use Livewire wire:navigate.
 *
 * Filament (and Pulse) ship their own Alpine stores/assets. SPA-navigating into
 * those panels from the front app leaves bindings like $store.sidebar undefined.
 */
final class SpaNavigate
{
    public static function allowed(string $url): bool
    {
        return ! self::requiresFullPageLoad($url);
    }

    public static function requiresFullPageLoad(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = $url;
        }

        foreach (self::fullPagePathPrefixes() as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Path prefixes that must never be SPA-swapped into the front app Alpine runtime.
     *
     * @return list<string>
     */
    public static function fullPagePathPrefixes(): array
    {
        $prefixes = [];

        foreach ([config('filament.admin_path'), config('pulse.path')] as $segment) {
            if (! is_string($segment) || $segment === '') {
                continue;
            }

            $normalized = '/'.trim($segment, '/');

            if ($normalized !== '/') {
                $prefixes[] = $normalized;
            }
        }

        return $prefixes;
    }
}
