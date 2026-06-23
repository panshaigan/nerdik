import { captureBrowserTimezone } from './browser-timezone';

const LOGIN_FORM_ID = 'ui-auth-login-form';
const LOGIN_SUBMIT_ID = 'ui-auth-login-submit';

export function shouldStayLocked(effects) {
    return Boolean(effects?.redirect);
}

function getLoginForm() {
    return document.getElementById(LOGIN_FORM_ID);
}

function getLoginAlpineData(form = getLoginForm()) {
    if (! form?._x_dataStack?.length) {
        return null;
    }

    return form._x_dataStack[0];
}

function lockLoginForm() {
    const data = getLoginAlpineData();

    if (data) {
        data.submitting = true;
    }

    const button = document.getElementById(LOGIN_SUBMIT_ID);

    if (button) {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    }
}

function unlockLoginForm() {
    const data = getLoginAlpineData();

    if (data) {
        data.submitting = false;
        data.navigating = false;
    }

    const button = document.getElementById(LOGIN_SUBMIT_ID);

    if (button) {
        button.disabled = false;
        button.removeAttribute('aria-busy');
    }
}

function isLoginComponentCommit(form, component) {
    const root = form.closest('[wire\\:id]');

    return root !== null && root.getAttribute('wire:id') === component.id;
}

let commitHookRegistered = false;

function registerLoginCommitHook() {
    if (commitHookRegistered || typeof window.Livewire?.hook !== 'function') {
        return;
    }

    commitHookRegistered = true;

    window.Livewire.hook('commit', ({ component, succeed, fail }) => {
        const form = getLoginForm();

        if (! form || ! isLoginComponentCommit(form, component)) {
            return;
        }

        fail(() => unlockLoginForm());

        succeed(({ effects }) => {
            if (! shouldStayLocked(effects)) {
                unlockLoginForm();
            }
        });
    });
}

let authLoginTimezoneAbort;

function initAuthLoginTimezone() {
    authLoginTimezoneAbort?.abort();
    authLoginTimezoneAbort = new AbortController();
    const signal = authLoginTimezoneAbort.signal;

    const timezone = captureBrowserTimezone();

    if (timezone === '') {
        return;
    }

    const enhanceLink = (selector) => {
        const button = document.querySelector(selector);

        if (! button) {
            return;
        }

        button.addEventListener('click', () => {
            const href = button.getAttribute('href');

            if (! href) {
                return;
            }

            const url = new URL(href, window.location.origin);

            if (! url.searchParams.has('tz')) {
                url.searchParams.set('tz', timezone);
                button.setAttribute('href', url.pathname + url.search);
            }
        }, { signal });
    };

    enhanceLink('[data-ui="auth-login-google"]');
    enhanceLink('[data-ui="auth-login-facebook"]');
    enhanceLink('[data-ui="auth-login-discord"]');
}

function initAuthLoginForm() {
    registerLoginCommitHook();
    initAuthLoginTimezone();
}

document.addEventListener('livewire:init', registerLoginCommitHook);
document.addEventListener('livewire:initialized', registerLoginCommitHook);

document.addEventListener('livewire:navigate', () => {
    if (getLoginForm()) {
        lockLoginForm();
    }
});

document.addEventListener('livewire:navigating', () => authLoginTimezoneAbort?.abort());

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAuthLoginForm, { once: true });
} else {
    initAuthLoginForm();
}

document.addEventListener('livewire:navigated', initAuthLoginForm);
