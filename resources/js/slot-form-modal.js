import { initTagSelector } from './tags-selector';
import { initSlotMassForm } from './slot-mass-form';

/**
 * Run after AJAX-injected slot edit form HTML is inserted into the DOM.
 *
 * @param {ParentNode} root
 */
export function initSlotEditForm(root) {
    if (!root) {
        return;
    }

    const form = root.matches?.('form[data-slot-edit-form]')
        ? root
        : root.querySelector('form[data-slot-edit-form]') || root.closest('form[data-slot-edit-form]');
    if (!form) {
        return;
    }

    root.querySelectorAll('[data-tag-selector]').forEach((el) => {
        if (el.dataset.tsInitialized) {
            return;
        }
        initTagSelector(el);
    });

    if (form.hasAttribute('data-slot-mass-form')) {
        initSlotMassForm(form);
    }
}
