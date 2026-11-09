import '../css/app.css';
import './datetime-picker-bridge';
import './close-modals-on-navigate';
import { captureBrowserTimezone } from './browser-timezone';
import './auth-login-form';
import './auth-recaptcha';
import './copy-to-clipboard';
import './invite-user-search';
import './browse-search-state';

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

    let scheduled = false;
    const boot = () => {
        if (scheduled) {
            return;
        }

        scheduled = true;
        loadSentry();
    };

    ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach((eventName) => {
        window.addEventListener(eventName, boot, { once: true, passive: true, capture: true });
    });

    window.setTimeout(boot, 10000);
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

function scheduleActivityFormsPresent() {
    return document.querySelector('[data-ui="schedule-activity-form-host"], form[data-schedule-activity-form]') !== null;
}

function bootSlotForms() {
    const shouldLoad =
        scheduleActivityFormsPresent()
        || document.querySelector('[data-slot-mass-form], #slot-edit-modal') !== null;

    return loadOnce('slot-forms', shouldLoad, async () => {
        const [{ initSlotEditForm }, { initSlotMassForm, refreshScheduleActivityForm }] = await Promise.all([
            import('./slot-form-modal'),
            import('./slot-mass-form'),
        ]);

        window.initSlotEditForm = initSlotEditForm;
        window.initSlotMassForm = initSlotMassForm;
        window.refreshScheduleActivityForm = refreshScheduleActivityForm;

        const bootForms = () => {
            document.querySelectorAll('form[data-schedule-activity-form]').forEach((form) => {
                delete form.dataset.slotMassFormInitialized;
            });

            document.querySelectorAll('form[data-slot-mass-form], form[data-schedule-activity-form]').forEach((form) => {
                if (form.closest('#slot-edit-modal-body')) {
                    return;
                }

                if (form.dataset.slotMassFormInitialized === '1') {
                    return;
                }

                if (form.hasAttribute('data-slot-edit-form')) {
                    initSlotEditForm(form);
                } else {
                    initSlotMassForm(form);
                }
            });
        };

        bootForms();

        if (typeof window.Livewire !== 'undefined' && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('morphed', () => {
                requestAnimationFrame(bootForms);
            });
        }
    });
}

function ensureScheduleActivityFormReady() {
    bootSlotForms();

    return moduleLoads.get('slot-forms') ?? Promise.resolve();
}

window.ensureScheduleActivityFormReady = ensureScheduleActivityFormReady;

function tagSelectorNeedsImmediateBoot(root) {
    const configElement = root.querySelector('script[type="application/json"][data-ts-config]');

    if (! configElement) {
        return false;
    }

    try {
        const config = JSON.parse(configElement.textContent || '{}');

        return (Array.isArray(config.initialSelectedIds) && config.initialSelectedIds.length > 0)
            || (Array.isArray(config.initialNewTags) && config.initialNewTags.length > 0)
            || Boolean(config.browseTextSearch?.value);
    } catch {
        return false;
    }
}

function loadTagSelectors() {
    return loadOnce('tag-selectors', true, () => import('./tags-selector').then(({ initTagSelector }) => {
        window.initTagSelector = initTagSelector;
        document.querySelectorAll('[data-tag-selector]').forEach((element) => initTagSelector(element));
    }));
}

function bootTagSelectors() {
    document.querySelectorAll('[data-tag-selector]').forEach((root) => {
        if (! (root instanceof HTMLElement) || root.dataset.tagSelectorLoaderBound === '1') {
            return;
        }

        root.dataset.tagSelectorLoaderBound = '1';

        if (tagSelectorNeedsImmediateBoot(root)) {
            loadTagSelectors();

            return;
        }

        root.addEventListener('pointerenter', loadTagSelectors, { once: true });
        root.addEventListener('pointerdown', loadTagSelectors, { once: true });
        root.addEventListener('focusin', loadTagSelectors, { once: true });
    });
}

function bootActivityTagPickersFeature() {
    if (document.querySelector('[data-activity-tag-picker]') === null) {
        return;
    }

    // Re-call boot on every feature pass: loadOnce only caches the import; fresh morph/
    // navigate roots (without data-atp-initialized) still need initActivityTagPicker.
    loadOnce('activity-tag-pickers', true, () => import('./activity-tag-picker')).then((mod) => {
        mod?.bootActivityTagPickers?.();
    });
}

let livewireActivityTagPickerMorphHookRegistered = false;

function registerLivewireActivityTagPickerMorphHook() {
    if (livewireActivityTagPickerMorphHookRegistered) {
        return true;
    }
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.hook !== 'function') {
        return false;
    }
    livewireActivityTagPickerMorphHookRegistered = true;
    window.Livewire.hook('morphed', () => {
        requestAnimationFrame(() => bootActivityTagPickersFeature());
    });

    return true;
}

document.addEventListener('livewire:init', registerLivewireActivityTagPickerMorphHook);
document.addEventListener('livewire:initialized', registerLivewireActivityTagPickerMorphHook);
document.addEventListener('DOMContentLoaded', registerLivewireActivityTagPickerMorphHook);
window.addEventListener('load', registerLivewireActivityTagPickerMorphHook);
registerLivewireActivityTagPickerMorphHook();

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

            const target = event.target instanceof Element ? event.target : null;
            const trigger = target?.closest('[data-browse-date-range-trigger]');
            const rangeInput = target?.closest('[data-browse-date-range-input]');

            if (!(trigger instanceof HTMLElement) && !(rangeInput instanceof HTMLInputElement)) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            await ensureReady();

            if (trigger instanceof HTMLElement) {
                trigger.click();

                return;
            }

            const input = root.querySelector('[data-browse-date-range-input]');
            if (input instanceof HTMLInputElement) {
                input.focus();
                input.click();
            }
        }, { capture: true });
    });
}

function bootTinyMceFieldChrome() {
    const shouldLoad = document.querySelector(
        '.tox-tinymce, script[src*="tinymce"], [data-ui*="editor"], form[data-activity-form], form[data-event-form], form[data-org-form]',
    ) !== null || typeof window.tinymce !== 'undefined';

    loadOnce('tinymce-field-chrome', shouldLoad, () => import('./tinymce-field-chrome'));
}

function pageNeedsImageCropper() {
    return document.querySelector(
        '#ui-image-crop-modal, [data-image-crop-dropzone], form[data-activity-form], form[data-event-form], form[data-org-form], #ui-profile-avatar-form',
    ) !== null;
}

function bootImageCropperFeature() {
    if (! pageNeedsImageCropper()) {
        return;
    }

    // Import only; image-cropper.js binds document listeners once (event delegation).
    // Do not re-boot on every MutationObserver pass — that aborts listeners mid file pick.
    loadOnce('image-cropper', true, () => import('./image-cropper'));
}

function bootFeatureModules() {
    bootTinyMceFieldChrome();
    bootImageCropperFeature();
    // Re-call bootMaps on every feature pass: loadOnce only caches the import; Livewire
    // modals (e.g. user preview play-area map) insert map roots after the first page boot.
    loadOnce(
        'maps',
        document.querySelector('[data-event-places-unified], [data-event-show-map-root], [data-browse-events-map], [data-place-location-map], [data-play-area-map], [data-user-play-area-preview-map]') !== null,
        () => import('./maps-init'),
    ).then((mod) => {
        mod?.bootMaps?.();
    });
    bootTagSelectors();
    bootActivityTagPickersFeature();
    loadOnce(
        'datetime-pickers',
        document.querySelector('input[data-ui-datetime]') !== null || scheduleActivityFormsPresent(),
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

function registerScheduleActivityModalHooks() {
    if (typeof window.Livewire?.hook !== 'function') {
        return;
    }

    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            queueMicrotask(() => {
                const form = document.getElementById('event-schedule-activity-form');
                const dialog = form?.closest('dialog');
                if (!form || !dialog?.open) {
                    return;
                }

                void window.refreshScheduleActivityForm?.(form);
            });
        });
    });
}

document.addEventListener('livewire:init', registerScheduleActivityModalHooks);
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
