import '../css/app.css';
import './close-modals-on-navigate';
import { captureBrowserTimezone } from './browser-timezone';
import './auth-login-form';
import './auth-recaptcha';
import './copy-to-clipboard';
import './tinymce-field-chrome';
import './invite-user-search';

const moduleLoads = new Map();

let sentryModuleLoad = null;

function loadSentry() {
    if (! window.__nerdikSentry?.dsn) {
        return Promise.resolve(null);
    }

    if (sentryModuleLoad === null) {
        sentryModuleLoad = import('./sentry')
            .then((sentry) => {
                sentry.bootSentry();

                return sentry;
            })
            .catch((error) => {
                sentryModuleLoad = null;
                console.error('Failed to load browser monitoring', error);

                return null;
            });
    }

    return sentryModuleLoad;
}

function scheduleSentryBoot() {
    if (! window.__nerdikSentry?.dsn) {
        return;
    }

    const boot = () => {
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(() => loadSentry(), { timeout: 3000 });

            return;
        }

        window.setTimeout(() => loadSentry(), 0);
    };

    if (document.readyState === 'complete') {
        boot();
    } else {
        window.addEventListener('load', boot, { once: true });
    }
}

function captureLivewireFailure(status, body) {
    loadSentry().then((sentry) => sentry?.captureLivewireFailure(status, body));
}

scheduleSentryBoot();

function loadOnce(key, shouldLoad, loader) {
    if (! shouldLoad || moduleLoads.has(key)) {
        return moduleLoads.get(key) ?? Promise.resolve();
    }

    const load = loader().catch((error) => {
        moduleLoads.delete(key);
        console.error(`Failed to load frontend module: ${key}`, error);
    });

    moduleLoads.set(key, load);

    return load;
}

function bootSlotForms() {
    const shouldLoad = document.querySelector('[data-slot-mass-form], #slot-edit-modal') !== null;

    loadOnce('slot-forms', shouldLoad, async () => {
        const [{ initSlotEditForm }, { initSlotMassForm }] = await Promise.all([
            import('./slot-form-modal'),
            import('./slot-mass-form'),
        ]);

        window.initSlotEditForm = initSlotEditForm;
        window.initSlotMassForm = initSlotMassForm;

        document.querySelectorAll('form[data-slot-mass-form]').forEach((form) => {
            if (form.closest('#slot-edit-modal-body')) {
                return;
            }

            if (form.hasAttribute('data-slot-edit-form')) {
                initSlotEditForm(form);
            } else {
                initSlotMassForm(form);
            }
        });
    });
}

function bootTagSelectors() {
    const shouldLoad = document.querySelector('[data-tag-selector]') !== null;

    loadOnce('tag-selectors', shouldLoad, () => import('./tags-selector').then(({ initTagSelector }) => {
        window.initTagSelector = initTagSelector;
        document.querySelectorAll('[data-tag-selector]').forEach((element) => initTagSelector(element));
    }));
}

function bootBrowseDateRangePickersOnDemand() {
    document.querySelectorAll('[data-browse-date-range]').forEach((root) => {
        if (!(root instanceof HTMLElement) || root.dataset.dateRangeLoaderBound === '1') {
            return;
        }

        root.dataset.dateRangeLoaderBound = '1';
        let ready = false;

        const ensureReady = () => loadOnce(
            'browse-date-range-picker',
            true,
            () => import('./browse-date-range-picker').then(({ bootBrowseDateRangePickers }) => {
                bootBrowseDateRangePickers();
            }),
        ).then(() => {
            ready = true;
        });

        root.addEventListener('pointerenter', ensureReady, { once: true });
        root.addEventListener('focusin', ensureReady, { once: true });
        root.addEventListener('click', async (event) => {
            if (ready) {
                return;
            }

            const trigger = event.target instanceof Element
                ? event.target.closest('[data-browse-date-range-trigger]')
                : null;

            if (!(trigger instanceof HTMLElement)) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            await ensureReady();
            trigger.click();
        }, { capture: true });
    });
}

function bootFeatureModules() {
    loadOnce(
        'image-cropper',
        document.querySelector('[data-image-crop-dropzone]') !== null,
        () => import('./image-cropper').then(({ bootImageCropper }) => bootImageCropper()),
    );
    loadOnce(
        'maps',
        document.querySelector('[data-event-places-unified], [data-event-show-map-root], [data-browse-events-map]') !== null,
        () => import('./maps-init').then(({ bootMaps }) => bootMaps()),
    );
    bootTagSelectors();
    loadOnce(
        'activity-tag-pickers',
        document.querySelector('[data-activity-tag-picker]') !== null,
        () => import('./activity-tag-picker').then(({ bootActivityTagPickers }) => bootActivityTagPickers()),
    );
    loadOnce(
        'datetime-pickers',
        document.querySelector('input[type="datetime-local"]') !== null,
        () => import('./datetime-picker').then(({ bootDateTimePickers }) => bootDateTimePickers()),
    );
    loadOnce(
        'event-show-slot-forms',
        document.querySelector('[data-event-show-async-mass]') !== null,
        () => import('./event-show-slot-forms').then(({ initEventShowSlotForms }) => initEventShowSlotForms()),
    );
    loadOnce(
        'proposal-event-autocomplete',
        document.querySelector('[data-proposal-event-autocomplete]') !== null,
        () => import('./activities/proposal-event-autocomplete').then(
            ({ bootProposalEventAutocomplete }) => bootProposalEventAutocomplete(),
        ),
    );
    bootBrowseDateRangePickersOnDemand();
    loadOnce(
        'browse-search-state',
        window.location.pathname === '/search',
        () => import('./browse-search-state').then(({ initBrowseSearchState }) => initBrowseSearchState()),
    );

    bootSlotForms();
    bootScopedRealtimeModules();
}

let realtimeReady = null;

function echoIsConfigured() {
    return Boolean(window.__nerdikEchoConfig?.key || import.meta.env.VITE_REVERB_APP_KEY);
}

function bootRealtimeModules() {
    if (! document.body?.dataset?.userId || ! echoIsConfigured()) {
        return Promise.resolve();
    }

    if (realtimeReady !== null) {
        return realtimeReady;
    }

    realtimeReady = import('./echo')
        .then(() => Promise.all([
            import('./notifications-echo').then(({ subscribeToUserNotifications }) => subscribeToUserNotifications()),
            import('./session-invalidated-echo').then(({ subscribeToSessionInvalidated }) => subscribeToSessionInvalidated()),
            import('./avatar-ready-echo').then(({ subscribeToAvatarReady }) => subscribeToAvatarReady()),
        ]))
        .catch((error) => {
            realtimeReady = null;
            console.error('Failed to load realtime modules', error);
        });

    return realtimeReady;
}

function bootScopedRealtimeModules() {
    if (! document.body?.dataset?.userId || ! echoIsConfigured()) {
        return;
    }

    bootRealtimeModules().then(() => {
        loadOnce(
            'activity-realtime',
            document.querySelector('[data-show-activity-id]') !== null,
            () => import('./activities-echo').then(
                ({ subscribeActivityParticipationEchoChannel }) => subscribeActivityParticipationEchoChannel(),
            ),
        );
        loadOnce(
            'event-plan-realtime',
            document.querySelector('[data-show-event-id]') !== null,
            () => import('./events-plan-counters-echo').then(
                ({ subscribeEventPlanCounterChannels }) => subscribeEventPlanCounterChannels(),
            ),
        );
    });
}

let featureBootQueued = false;

function queueFeatureBoot() {
    if (featureBootQueued) {
        return;
    }

    featureBootQueued = true;
    queueMicrotask(() => {
        featureBootQueued = false;
        bootFeatureModules();
    });
}

function observeFeatureMarkup() {
    queueFeatureBoot();
    bootRealtimeModules();

    if (! document.body || window.__nerdikFeatureObserver) {
        return;
    }

    window.__nerdikFeatureObserver = new MutationObserver(queueFeatureBoot);
    window.__nerdikFeatureObserver.observe(document.body, { childList: true, subtree: true });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observeFeatureMarkup, { once: true });
} else {
    observeFeatureMarkup();
}

document.addEventListener('livewire:navigated', queueFeatureBoot);
function handleLivewireAuthFailure(preventDefault) {
    if (window.__nerdikSessionExpiredHandled) {
        preventDefault();

        return true;
    }

    window.__nerdikSessionExpiredHandled = true;
    preventDefault();
    window.dispatchEvent(new CustomEvent('session-expired'));

    return true;
}

function isAuthRelatedLivewireError(status, content) {
    if (status === 401 || status === 419) {
        return true;
    }

    if (status !== 403 || typeof content !== 'string' || content.trim() === '') {
        return false;
    }

    try {
        const payload = JSON.parse(content);
        const message = typeof payload?.message === 'string' ? payload.message.toLowerCase() : '';

        return message.includes('unauthorized')
            || message.includes('unauthenticated')
            || message.includes('csrf')
            || message.includes('session');
    } catch {
        return content.toLowerCase().includes('unauthorized')
            || content.toLowerCase().includes('unauthenticated');
    }
}

function shouldSuppressLivewireErrorModal(status, content) {
    if (isAuthRelatedLivewireError(status, content)) {
        return true;
    }

    if (typeof content !== 'string' || content.trim() === '') {
        return false;
    }

    const trimmed = content.trim();

    return trimmed.startsWith('{') || trimmed.startsWith('[');
}

function registerLivewireRequestFailureHandlers() {
    if (typeof window.Livewire === 'undefined') {
        return;
    }

    if (window.__nerdikLivewireFailureHandlersRegistered) {
        return;
    }

    window.__nerdikLivewireFailureHandlersRegistered = true;

    if (typeof window.Livewire.interceptRequest === 'function') {
        window.Livewire.interceptRequest(({ onError }) => {
            onError(({ response, body, preventDefault }) => {
                if (!response) {
                    return;
                }

                if (isAuthRelatedLivewireError(response.status, body)) {
                    handleLivewireAuthFailure(preventDefault);

                    return;
                }

                if (shouldSuppressLivewireErrorModal(response.status, body)) {
                    preventDefault();
                    console.error('Livewire request failed', response.status, body);
                    captureLivewireFailure(response.status, body);
                }
            });
        });
    }

    if (typeof window.Livewire.hook === 'function') {
        window.Livewire.hook('request', ({ fail }) => {
            fail(({ status, content, preventDefault }) => {
                if (isAuthRelatedLivewireError(status, content)) {
                    handleLivewireAuthFailure(preventDefault);

                    return;
                }

                if (shouldSuppressLivewireErrorModal(status, content)) {
                    preventDefault();
                    console.error('Livewire request failed', status, content);
                    captureLivewireFailure(status, content);
                }
            });
        });
    }
}

document.addEventListener('livewire:init', registerLivewireRequestFailureHandlers);
document.addEventListener('DOMContentLoaded', registerLivewireRequestFailureHandlers);

function bootBrowserTimezone() {
    captureBrowserTimezone();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootBrowserTimezone);
} else {
    bootBrowserTimezone();
}

document.addEventListener('livewire:navigated', bootBrowserTimezone);
