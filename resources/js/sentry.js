import * as Sentry from '@sentry/browser';

/**
 * Livewire rejects cancelled/aborted/failed action promises with this shape
 * instead of an Error instance.
 *
 * @param {unknown} reason
 * @returns {reason is { status: unknown, body: unknown, json: unknown, errors: unknown }}
 */
export function isLivewireActionRejection(reason) {
    return reason !== null
        && typeof reason === 'object'
        && !Array.isArray(reason)
        && Object.prototype.hasOwnProperty.call(reason, 'status')
        && Object.prototype.hasOwnProperty.call(reason, 'body')
        && Object.prototype.hasOwnProperty.call(reason, 'json')
        && Object.prototype.hasOwnProperty.call(reason, 'errors');
}

/**
 * Cancelled or network-aborted Livewire requests (e.g. hard redirect mid-flight).
 *
 * @param {unknown} reason
 */
export function isTransientLivewireRejection(reason) {
    if (!isLivewireActionRejection(reason)) {
        return false;
    }

    return reason.status == null || reason.status === 0;
}

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
        // Facebook/Instagram Android IAB native-bridge noise (see config/sentry.php browser.*)
        ignoreErrors: Array.isArray(config.ignoreErrors) ? config.ignoreErrors : [],
        denyUrls: Array.isArray(config.denyUrls) ? config.denyUrls : [],
        beforeSend(event, hint) {
            if (isTransientLivewireRejection(hint?.originalException)) {
                return null;
            }

            return event;
        },
    });

    if (!window.__nerdikLivewireRejectionGuard) {
        window.__nerdikLivewireRejectionGuard = true;
        window.addEventListener('unhandledrejection', (event) => {
            if (isTransientLivewireRejection(event.reason)) {
                event.preventDefault();
            }
        });
    }
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
