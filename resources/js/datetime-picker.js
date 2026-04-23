function canUseNativePicker(input) {
    return !!input
        && input.tagName === 'INPUT'
        && input.type === 'datetime-local'
        && !input.disabled
        && !input.readOnly
        && typeof input.showPicker === 'function';
}

function stepSecondsFromDom() {
    const minutes = Number(document.body?.dataset?.datetimeMinuteStep || '5');
    const safeMinutes = Number.isFinite(minutes) && minutes > 0 ? minutes : 5;

    return Math.max(1, Math.round(safeMinutes * 60));
}

function applyStepIfMissing(input) {
    if (!input || input.tagName !== 'INPUT' || input.type !== 'datetime-local') {
        return;
    }
    if (!input.getAttribute('step')) {
        input.setAttribute('step', String(stepSecondsFromDom()));
    }
}

function tomorrowNoonLocalValue() {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    const pad = (n) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T12:00`;
}

function openNativePicker(input) {
    if (!canUseNativePicker(input)) {
        return;
    }

    if (document.activeElement !== input) {
        input.focus({ preventScroll: true });
    }

    input.showPicker();
}

export function bootDateTimePickers() {
    if (window.__datetimePickerUiBound === true) {
        return;
    }
    window.__datetimePickerUiBound = true;

    const syncAllDatetimeSteps = () => {
        document.querySelectorAll('input[type="datetime-local"]').forEach((input) => {
            applyStepIfMissing(input);
        });
    };

    syncAllDatetimeSteps();
    document.addEventListener('livewire:navigated', syncAllDatetimeSteps);

    document.addEventListener(
        'pointerdown',
        (event) => {
            const directInput = event.target.closest('input[type="datetime-local"]');
            const shell = event.target.closest('label.input');
            const input = directInput || shell?.querySelector('input[type="datetime-local"]');
            if (!input) {
                return;
            }
            applyStepIfMissing(input);

            if (!directInput && !shell?.contains(event.target)) {
                return;
            }

            if (input.hasAttribute('data-selfhost-start-input') && (!input.value || input.value.trim() === '')) {
                input.value = tomorrowNoonLocalValue();
                input.dataset.selfhostStartSeeded = input.value;
                input.dataset.selfhostStartChanged = '0';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }

            event.preventDefault();
            openNativePicker(input);
        },
        true,
    );

    document.addEventListener(
        'input',
        (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-selfhost-start-input')) {
                return;
            }
            if (input.dataset.selfhostStartSeeded) {
                input.dataset.selfhostStartChanged = '1';
            }
        },
        true,
    );

    document.addEventListener(
        'blur',
        (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-selfhost-start-input')) {
                return;
            }
            // Keep seeded value on blur so users can intentionally accept tomorrow 12:00
            // by just opening the picker and clicking outside.
            delete input.dataset.selfhostStartSeeded;
            delete input.dataset.selfhostStartChanged;
        },
        true,
    );
}
