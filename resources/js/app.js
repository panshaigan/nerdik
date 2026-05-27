import './bootstrap';
import './tinymce-field-chrome';
import './image-cropper';
import './notifications-echo';
import './activities-echo';
import './events-plan-counters-echo';
import './maps-init';
import './tags-init';
import './session-invalidated-echo';
import { bootActivityTagPickers } from './activity-tag-picker';
import { bootDateTimePickers } from './datetime-picker';
import { initEventShowSlotForms } from './event-show-slot-forms';
import { bootProposalEventAutocomplete } from './activities/proposal-event-autocomplete';
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

function registerSessionExpiredInterceptor() {
    if (
        typeof window.Livewire === 'undefined'
        || typeof window.Livewire.interceptRequest !== 'function'
    ) {
        return;
    }

    if (window.__nerdikSessionExpiredInterceptorRegistered) {
        return;
    }

    window.__nerdikSessionExpiredInterceptorRegistered = true;

    window.Livewire.interceptRequest(({ onError }) => {
        onError(({ response, preventDefault }) => {
            if (
                !response
                || window.__nerdikSessionExpiredHandled
                || (response.status !== 401 && response.status !== 419)
            ) {
                return;
            }

            preventDefault();
            window.__nerdikSessionExpiredHandled = true;
            window.dispatchEvent(new CustomEvent('session-expired'));
        });
    });
}

document.addEventListener('livewire:init', registerSessionExpiredInterceptor);
document.addEventListener('DOMContentLoaded', registerSessionExpiredInterceptor);
