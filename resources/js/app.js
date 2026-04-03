import './bootstrap';
import './maps-init';
import './tags-init';
import { initEventShowSlotForms } from './event-show-slot-forms';
import { initSlotEditForm } from './slot-form-modal';
import { initSlotMassForm } from './slot-mass-form';
import Quill from 'quill';

window.Quill = Quill;
window.initSlotEditForm = initSlotEditForm;
window.initSlotMassForm = initSlotMassForm;

initEventShowSlotForms();

document.addEventListener('DOMContentLoaded', () => {
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
