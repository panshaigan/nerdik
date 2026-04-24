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

function pad2(n) {
    return String(n).padStart(2, '0');
}

function formatLocalDateTime(d) {
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}T${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
}

function eventStartInputFromContext(input) {
    const form = input?.closest?.('form[data-event-form]');

    return form?.querySelector('[data-event-start-at]') ?? document.querySelector('[data-event-start-at]');
}

/** Default for empty event end: strictly after start (Laravel `after:starts_at`) or tomorrow noon. */
function defaultEventEndSeedLocalValue(startInput) {
    const raw = startInput?.value?.trim();
    if (!raw) {
        return tomorrowNoonLocalValue();
    }
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) {
        return tomorrowNoonLocalValue();
    }
    const addMinutes = Math.max(1, Math.ceil(stepSecondsFromDom() / 60));
    d.setMinutes(d.getMinutes() + addMinutes);

    return formatLocalDateTime(d);
}

function isEmptyDatetimeValue(input) {
    return !input?.value || String(input.value).trim() === '';
}

function seedEventStartIfEmpty(input) {
    if (!input.hasAttribute('data-event-start-at') || !isEmptyDatetimeValue(input)) {
        return;
    }
    input.value = tomorrowNoonLocalValue();
    input.dataset.eventStartSeeded = input.value;
    input.dataset.eventStartChanged = '0';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

function seedEventEndIfEmpty(input) {
    if (!input.hasAttribute('data-event-ends-at') || !isEmptyDatetimeValue(input)) {
        return;
    }
    const startEl = eventStartInputFromContext(input);
    input.value = defaultEventEndSeedLocalValue(startEl);
    input.dataset.eventEndSeeded = input.value;
    input.dataset.eventEndChanged = '0';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
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

            seedEventStartIfEmpty(input);
            seedEventEndIfEmpty(input);

            event.preventDefault();
            openNativePicker(input);
        },
        true,
    );

    document.addEventListener(
        'input',
        (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement)) {
                return;
            }
            if (input.hasAttribute('data-selfhost-start-input') && input.dataset.selfhostStartSeeded) {
                input.dataset.selfhostStartChanged = '1';
            }
            if (input.hasAttribute('data-event-start-at') && input.dataset.eventStartSeeded) {
                input.dataset.eventStartChanged = '1';
            }
            if (input.hasAttribute('data-event-ends-at') && input.dataset.eventEndSeeded) {
                input.dataset.eventEndChanged = '1';
            }
        },
        true,
    );

    document.addEventListener(
        'blur',
        (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement)) {
                return;
            }
            if (input.hasAttribute('data-selfhost-start-input')) {
                // Keep seeded value on blur so users can intentionally accept tomorrow 12:00
                // by just opening the picker and clicking outside.
                delete input.dataset.selfhostStartSeeded;
                delete input.dataset.selfhostStartChanged;
            }
            if (input.hasAttribute('data-event-start-at')) {
                delete input.dataset.eventStartSeeded;
                delete input.dataset.eventStartChanged;
            }
            if (input.hasAttribute('data-event-ends-at')) {
                delete input.dataset.eventEndSeeded;
                delete input.dataset.eventEndChanged;
            }
        },
        true,
    );
}
