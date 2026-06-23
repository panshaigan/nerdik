import './bootstrap';
import './close-modals-on-navigate';
import { captureBrowserTimezone } from './browser-timezone';
import './auth-login-form';
import './copy-to-clipboard';
import './tinymce-field-chrome';
import './image-cropper';
import './notifications-echo';
import './activities-echo';
import './events-plan-counters-echo';
import './maps-init';
import './tags-init';
import './browse-search-state';
import './session-invalidated-echo';
import './avatar-ready-echo';
import { bootActivityTagPickers } from './activity-tag-picker';
import { bootDateTimePickers } from './datetime-picker';
import { initEventShowSlotForms } from './event-show-slot-forms';
import { bootProposalEventAutocomplete } from './activities/proposal-event-autocomplete';
import './invite-user-search';
import { initSlotEditForm } from './slot-form-modal';
import { initSlotMassForm } from './slot-mass-form';

window.initSlotEditForm = initSlotEditForm;
window.initSlotMassForm = initSlotMassForm;

initEventShowSlotForms();
bootDateTimePickers();

function bootSlotMassForms() {
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
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootSlotMassForms);
} else {
    bootSlotMassForms();
}
bootProposalEventAutocomplete();

document.addEventListener('livewire:navigated', bootSlotMassForms);
document.addEventListener('livewire:navigated', () => bootProposalEventAutocomplete());

function registerActivityTagPickerMorphHook() {
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.hook !== 'function') {
        return;
    }
    window.Livewire.hook('morph.updated', () => {
        queueMicrotask(() => bootActivityTagPickers());
    });
}

document.addEventListener('DOMContentLoaded', () => bootActivityTagPickers());
document.addEventListener('DOMContentLoaded', () => bootProposalEventAutocomplete());
document.addEventListener('livewire:navigated', () => bootActivityTagPickers());
document.addEventListener('livewire:init', registerActivityTagPickerMorphHook);
document.addEventListener('livewire:initialized', registerActivityTagPickerMorphHook);

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
