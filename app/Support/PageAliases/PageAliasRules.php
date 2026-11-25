<?php

declare(strict_types=1);

namespace App\Support\PageAliases;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PageAliasRules
{
    public const string FALLBACK_ROUTE_NAME = 'page-alias.redirect';

    public static function normalizeAlias(string $alias): string
    {
        $normalized = strtolower(trim($alias));

        if (! preg_match('/^[a-z0-9](?:-?[a-z0-9])*$/', $normalized)) {
            throw ValidationException::withMessages([
                'alias' => [__('ui.page_aliases.invalid_alias_format')],
            ]);
        }

        $length = strlen($normalized);
        if ($length < 2 || $length > 50) {
            throw ValidationException::withMessages([
                'alias' => [__('ui.page_aliases.invalid_alias_length')],
            ]);
        }

        return $normalized;
    }

    public static function isReserved(string $alias): bool
    {
        $alias = strtolower(trim($alias));

        /** @var list<string> $reserved */
        $reserved = config('page_aliases.reserved', []);
        if (in_array($alias, $reserved, true)) {
            return true;
        }

        return self::routeMatchIsNonFallback('/'.$alias);
    }

    public static function normalizeTargetPath(string $path): string
    {
        $trimmed = trim($path);

        if ($trimmed === '' || ! str_starts_with($trimmed, '/')) {
            throw ValidationException::withMessages([
                'target_path' => [__('ui.page_aliases.invalid_target_path')],
            ]);
        }

        if (str_starts_with($trimmed, '//') || str_contains($trimmed, '://')) {
            throw ValidationException::withMessages([
                'target_path' => [__('ui.page_aliases.invalid_target_path')],
            ]);
        }

        $pathOnly = $trimmed;
        $query = '';

        if (($hashPos = strpos($pathOnly, '#')) !== false) {
            $pathOnly = substr($pathOnly, 0, $hashPos);
        }

        if (($queryPos = strpos($pathOnly, '?')) !== false) {
            $query = substr($pathOnly, $queryPos);
            $pathOnly = substr($pathOnly, 0, $queryPos);
        }

        if ($pathOnly === '') {
            $pathOnly = '/';
        }

        if (! self::routeMatchIsNonFallback($pathOnly)) {
            throw ValidationException::withMessages([
                'target_path' => [__('ui.page_aliases.target_not_routable')],
            ]);
        }

        return $pathOnly.$query;
    }

    public static function routeMatchIsNonFallback(string $uri): bool
    {
        $request = Request::create($uri, 'GET');

        try {
            $route = Route::getRoutes()->match($request);

            return $route->getName() !== self::FALLBACK_ROUTE_NAME;
        } catch (MethodNotAllowedHttpException) {
            return true;
        } catch (NotFoundHttpException) {
            return false;
        }
    }
}
