import './bootstrap';
import './maps-init';
import './tags-init';
import { bootActivityTagPickers } from './activity-tag-picker';
import { initEventShowSlotForms } from './event-show-slot-forms';
import { initSlotEditForm } from './slot-form-modal';
import { initSlotMassForm } from './slot-mass-form';

window.initSlotEditForm = initSlotEditForm;
window.initSlotMassForm = initSlotMassForm;

initEventShowSlotForms();

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

document.addEventListener('livewire:navigated', bootSlotMassForms);

function registerActivityTagPickerMorphHook() {
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.hook !== 'function') {
        return;
    }
    window.Livewire.hook('morph.updated', () => {
        queueMicrotask(() => bootActivityTagPickers());
    });
}

document.addEventListener('DOMContentLoaded', () => bootActivityTagPickers());
document.addEventListener('livewire:navigated', () => bootActivityTagPickers());
document.addEventListener('livewire:init', registerActivityTagPickerMorphHook);
document.addEventListener('livewire:initialized', registerActivityTagPickerMorphHook);
