const WIDGET_ATTR = 'data-nerdik-recaptcha';
const WIDGET_ID_ATTR = 'data-recaptcha-widget-id';

/**
 * @returns {boolean}
 */
function grecaptchaReady() {
    return typeof window.grecaptcha !== 'undefined' && typeof window.grecaptcha.render === 'function';
}

/**
 * @param {HTMLElement} el
 * @returns {boolean}
 */
function alreadyMounted(el) {
    if (el.getAttribute(WIDGET_ID_ATTR)) {
        return true;
    }

    return el.childNodes.length > 0;
}

/**
 * @param {HTMLElement} el
 */
function mountRecaptcha(el) {
    if (!grecaptchaReady() || alreadyMounted(el)) {
        return;
    }

    const sitekey = el.getAttribute('data-sitekey');
    const callbackName = el.getAttribute('data-callback');

    if (!sitekey) {
        return;
    }

    /** @type {Record<string, unknown>} */
    const options = { sitekey };

    if (callbackName && typeof window[callbackName] === 'function') {
        options.callback = window[callbackName];
    }

    try {
        const widgetId = window.grecaptcha.render(el, options);
        el.setAttribute(WIDGET_ID_ATTR, String(widgetId));
    } catch (error) {
        // Google throws when the placeholder is non-empty (double-init). Ignore that race.
        if (error instanceof Error && error.message.includes('placeholder element must be empty')) {
            return;
        }

        throw error;
    }
}

export function mountAuthRecaptchas() {
    document.querySelectorAll(`[${WIDGET_ATTR}]`).forEach((el) => {
        if (el instanceof HTMLElement) {
            mountRecaptcha(el);
        }
    });
}

export function resetAuthRecaptchas() {
    if (!grecaptchaReady() || typeof window.grecaptcha.reset !== 'function') {
        return;
    }

    document.querySelectorAll(`[${WIDGET_ATTR}]`).forEach((el) => {
        if (!(el instanceof HTMLElement)) {
            return;
        }

        const widgetId = el.getAttribute(WIDGET_ID_ATTR);

        if (widgetId !== null && widgetId !== '') {
            window.grecaptcha.reset(Number(widgetId));

            return;
        }

        window.grecaptcha.reset();
    });
}

let resetListenerRegistered = false;

function registerResetListener() {
    if (resetListenerRegistered || typeof window.Livewire?.on !== 'function') {
        return;
    }

    resetListenerRegistered = true;
    window.Livewire.on('reset-recaptcha', resetAuthRecaptchas);
}

/**
 * Google api.js onload (explicit render). Also used after wire:navigate when the script is skipped.
 */
window.nerdikRecaptchaOnload = () => {
    mountAuthRecaptchas();
};

document.addEventListener('livewire:init', registerResetListener);
document.addEventListener('livewire:navigated', () => {
    registerResetListener();
    mountAuthRecaptchas();
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAuthRecaptchas, { once: true });
} else {
    mountAuthRecaptchas();
}
