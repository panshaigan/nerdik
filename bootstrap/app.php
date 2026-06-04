<?php

use App\Http\Middleware\ForceLivewireJson;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StoreBrowsingReturnUrl;
use App\Support\Logging\RedactsSensitiveData;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\SetCacheHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('TRUSTED_PROXIES');

        if (filled($trustedProxies)) {
            $middleware->trustProxies(
                at: $trustedProxies === '*'
                    ? '*'
                    : array_map(trim(...), explode(',', (string) $trustedProxies)),
            );
        }

        $middleware->prependToGroup('web', ForceLivewireJson::class);
        $middleware->appendToGroup('web', StoreBrowsingReturnUrl::class);
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('web', SetCacheHeaders::class);
        $middleware->appendToGroup('web', SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            '_token',
        ]);

        $exceptions->context(function (Throwable $throwable, array $context): array {
            return RedactsSensitiveData::redactArray($context);
        });
    })->create();
