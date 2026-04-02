import './bootstrap';
import './maps-init';
import './tags-init';
import { initSlotEditForm } from './slot-form-modal';
import Quill from 'quill';

window.Quill = Quill;
window.initSlotEditForm = initSlotEditForm;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-slot-edit-form]').forEach((form) => {
        if (form.closest('#slot-edit-modal-body')) {
            return;
        }
        initSlotEditForm(form);
    });
});
