import Echo from 'laravel-echo';

import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * @returns {{ key: string, host: string, port: number, scheme: string } | null}
 */
function resolveEchoConfig() {
    const runtime = window.__nerdikEchoConfig;

    if (runtime?.key) {
        return {
            key: runtime.key,
            host: runtime.host,
            port: runtime.port ?? 443,
            scheme: runtime.scheme ?? 'https',
        };
    }

    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

    if (! reverbKey) {
        return null;
    }

    const port = Number(import.meta.env.VITE_REVERB_PORT ?? 443);

    return {
        key: reverbKey,
        host: import.meta.env.VITE_REVERB_HOST,
        port: Number.isFinite(port) ? port : 443,
        scheme: import.meta.env.VITE_REVERB_SCHEME ?? 'https',
    };
}

const echoConfig = resolveEchoConfig();

if (echoConfig) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: echoConfig.key,
        wsHost: echoConfig.host,
        wsPort: echoConfig.port ?? 80,
        wssPort: echoConfig.port ?? 443,
        forceTLS: echoConfig.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN':
                    typeof document !== 'undefined'
                        ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
                        : '',
            },
        },
        withCredentials: true,
    });
} else {
    window.Echo = null;
}
