/**
 * Always-on bridge so datetime fields work before the lazy flatpickr chunk finishes loading.
 */

function mightActivateDatetimePicker(target) {
    if (!(target instanceof Element)) {
        return false;
    }

    if (target.closest('input[data-ui-datetime]')) {
        return true;
    }

    if (target.closest('[data-schedule-activity-field]')) {
        return true;
    }

  if (target.closest('label.input')?.querySelector('input[data-ui-datetime]')) {
        return true;
    }

      if (target.closest('label.floating-label')?.querySelector('input[data-ui-datetime]')) {
        return true;
    }

    return false;
}

function loadDatetimePickerModule() {
    if (window.__nerdikDatetimePickerModule) {
        return window.__nerdikDatetimePickerModule;
    }

    window.__nerdikDatetimePickerModule = import('./datetime-picker').then((mod) => {
        mod.bootDateTimePickers();

        return mod;
    });

    return window.__nerdikDatetimePickerModule;
}

if (typeof window !== 'undefined' && window.__nerdikDatetimePickerBridge !== true) {
    window.__nerdikDatetimePickerBridge = true;

    document.addEventListener(
        'pointerdown',
        (event) => {
            if (!mightActivateDatetimePicker(event.target)) {
                return;
            }

            event.preventDefault();

            loadDatetimePickerModule()
                .then((mod) => {
                    mod.openPickerFromPointerEvent?.(event);
                })
                .catch((error) => {
                    console.error('Failed to open datetime picker', error);
                });
        },
        true,
    );

    document.addEventListener(
        'keydown',
        (event) => {
            if (event.key !== 'Enter' && event.key !== ' ' && event.key !== 'ArrowDown') {
                return;
            }

            const input = event.target;
            if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-ui-datetime')) {
                return;
            }

            event.preventDefault();

            loadDatetimePickerModule()
                .then((mod) => {
                    mod.openPicker?.(input);
                })
                .catch((error) => {
                    console.error('Failed to open datetime picker', error);
                });
        },
        true,
    );
}
