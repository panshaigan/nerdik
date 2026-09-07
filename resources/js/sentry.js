import * as Sentry from '@sentry/browser';

/**
 * Boot browser Sentry when the server injected a DSN (see x-sentry-config).
 */
export function bootSentry() {
    const config = window.__nerdikSentry;

    if (!config?.dsn || window.__nerdikSentryBooted) {
        return;
    }

    window.__nerdikSentryBooted = true;

    Sentry.init({
        dsn: config.dsn,
        environment: config.environment || undefined,
        release: config.release || undefined,
        tracesSampleRate: Number(config.tracesSampleRate ?? 0),
    });
}

/**
 * @param {number} status
 * @param {string|undefined} body
 */
export function captureLivewireFailure(status, body) {
    if (!window.__nerdikSentryBooted) {
        return;
    }

    Sentry.captureMessage(`Livewire request failed (${status})`, {
        level: 'error',
        extra: {
            status,
            body: typeof body === 'string' ? body.slice(0, 2000) : undefined,
        },
    });
}
