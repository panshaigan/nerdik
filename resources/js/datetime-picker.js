import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import '../css/vendor/flatpickr-theme.css';
import { Polish } from 'flatpickr/dist/l10n/pl.js';

/** @type {WeakMap<HTMLInputElement, import('flatpickr').Instance>} */
const instances = new WeakMap();

function minuteIncrementFromDom() {
    const minutes = Number(document.body?.dataset?.datetimeMinuteStep || '5');
    const safeMinutes = Number.isFinite(minutes) && minutes > 0 ? minutes : 5;

    return Math.max(1, Math.round(safeMinutes));
}

function useTwentyFourHourClock() {
    return (document.body?.dataset?.timeDisplayFormat || '24h') !== '12h';
}

function appLocale() {
    return document.body?.dataset?.locale || 'en';
}

function pad2(n) {
    return String(n).padStart(2, '0');
}

function formatLocalDateTime(d) {
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}T${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
}

/**
 * @param {string | undefined | null} raw
 * @returns {Date | null}
 */
function parseDatetimeLocal(raw) {
    if (!raw || typeof raw !== 'string') {
        return null;
    }

    const trimmed = raw.trim();
    if (!trimmed) {
        return null;
    }

    const match = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/);
    if (!match) {
        const fallback = new Date(trimmed);
        return Number.isNaN(fallback.getTime()) ? null : fallback;
    }

    const [, y, m, d, h, min, s] = match;

    return new Date(
        Number(y),
        Number(m) - 1,
        Number(d),
        Number(h),
        Number(min),
        s ? Number(s) : 0,
        0,
    );
}

function tomorrowNoonLocalValue() {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    d.setHours(12, 0, 0, 0);

    return formatLocalDateTime(d);
}

function eventStartInputFromContext(input) {
    const form = input?.closest?.('form[data-event-form]');

    return form?.querySelector('[data-event-start-at]') ?? document.querySelector('[data-event-start-at]');
}

/** Default for empty event end: match start exactly, or tomorrow noon. */
function defaultEventEndSeedLocalValue(startInput) {
    const raw = startInput?.value?.trim();
    if (!raw) {
        return tomorrowNoonLocalValue();
    }
    const d = parseDatetimeLocal(raw);
    if (!d) {
        return tomorrowNoonLocalValue();
    }

    return formatLocalDateTime(d);
}

function isEmptyDatetimeValue(input) {
    return !input?.value || String(input.value).trim() === '';
}

function dispatchValueEvents(input) {
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

function seedEventStartIfEmpty(input) {
    if (!input.hasAttribute('data-event-start-at') || !isEmptyDatetimeValue(input)) {
        return;
    }
    input.value = tomorrowNoonLocalValue();
    input.dataset.eventStartSeeded = input.value;
    input.dataset.eventStartChanged = '0';
    dispatchValueEvents(input);
}

function seedEventEndIfEmpty(input) {
    if (!input.hasAttribute('data-event-ends-at') || !isEmptyDatetimeValue(input)) {
        return;
    }
    const startEl = eventStartInputFromContext(input);
    input.value = defaultEventEndSeedLocalValue(startEl);
    input.dataset.eventEndSeeded = input.value;
    input.dataset.eventEndChanged = '0';
    dispatchValueEvents(input);
}

function seedSelfHostIfEmpty(input) {
    if (!input.hasAttribute('data-selfhost-start-input') || !isEmptyDatetimeValue(input)) {
        return;
    }
    input.value = tomorrowNoonLocalValue();
    input.dataset.selfhostStartSeeded = input.value;
    input.dataset.selfhostStartChanged = '0';
    dispatchValueEvents(input);
}

function seedBeforeOpen(input) {
    seedSelfHostIfEmpty(input);
    seedEventStartIfEmpty(input);
    seedEventEndIfEmpty(input);
}

/**
 * @param {HTMLInputElement} input
 */
function syncInstanceFromInput(input) {
    const fp = instances.get(input);
    if (!fp) {
        return;
    }

    const minDate = parseDatetimeLocal(input.getAttribute('min'));
    const maxDate = parseDatetimeLocal(input.getAttribute('max'));
    fp.set('minDate', minDate || undefined);
    fp.set('maxDate', maxDate || undefined);

    const parsed = parseDatetimeLocal(input.value);
    if (parsed) {
        fp.setDate(parsed, false);
    } else if (!input.value?.trim()) {
        fp.clear(false);
    }
}

/**
 * @param {HTMLInputElement} input
 * @returns {import('flatpickr').Instance}
 */
function ensureInstance(input) {
    const existing = instances.get(input);
    if (existing) {
        // Livewire may have replaced the node; drop stale instance.
        if (!input.isConnected) {
            existing.destroy();
            instances.delete(input);
        } else {
            syncInstanceFromInput(input);

            return existing;
        }
    }

    const locale = appLocale();
    const defaultDate = parseDatetimeLocal(input.value);
    const minDate = parseDatetimeLocal(input.getAttribute('min'));
    const maxDate = parseDatetimeLocal(input.getAttribute('max'));

    const fp = flatpickr(input, {
        enableTime: true,
        dateFormat: 'Y-m-d\\TH:i',
        time_24hr: useTwentyFourHourClock(),
        minuteIncrement: minuteIncrementFromDom(),
        disableMobile: true,
        allowInput: false,
        clickOpens: false,
        appendTo: document.body,
        defaultDate: defaultDate || undefined,
        minDate: minDate || undefined,
        maxDate: maxDate || undefined,
        ...(locale === 'pl' ? { locale: Polish } : {}),
        onChange(_selectedDates, dateStr) {
            if (input.value !== dateStr) {
                input.value = dateStr;
            }
            dispatchValueEvents(input);
        },
    });

    input.setAttribute('readonly', 'readonly');
    instances.set(input, fp);

    return fp;
}

/**
 * @param {HTMLInputElement} input
 */
function openPicker(input) {
    if (input.disabled) {
        return;
    }

    seedBeforeOpen(input);
    const fp = ensureInstance(input);
    syncInstanceFromInput(input);

    if (document.activeElement !== input) {
        input.focus({ preventScroll: true });
    }

    fp.open();
}

/**
 * @returns {HTMLInputElement | null}
 */
function datetimeInputFromEventTarget(target) {
    if (!(target instanceof Element)) {
        return null;
    }

    const directInput = target.closest('input[data-ui-datetime]');
    if (directInput instanceof HTMLInputElement) {
        return directInput;
    }

    const shell = target.closest('label.input');
    const nested = shell?.querySelector('input[data-ui-datetime]');

    return nested instanceof HTMLInputElement ? nested : null;
}

function syncMountedDatetimePickers() {
    document.querySelectorAll('input[data-ui-datetime]').forEach((el) => {
        if (el instanceof HTMLInputElement && instances.has(el)) {
            syncInstanceFromInput(el);
        }
    });
}

export function bootDateTimePickers() {
    if (window.__datetimePickerUiBound === true) {
        syncMountedDatetimePickers();

        return;
    }
    window.__datetimePickerUiBound = true;

    document.addEventListener('livewire:navigated', syncMountedDatetimePickers);

    document.addEventListener(
        'pointerdown',
        (event) => {
            const input = datetimeInputFromEventTarget(event.target);
            if (!input) {
                return;
            }

            const directInput = event.target instanceof Element
                ? event.target.closest('input[data-ui-datetime]')
                : null;
            const shell = event.target instanceof Element
                ? event.target.closest('label.input')
                : null;

            if (!directInput && !shell?.contains(event.target)) {
                return;
            }

            event.preventDefault();
            openPicker(input);
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
            openPicker(input);
        },
        true,
    );

    document.addEventListener(
        'input',
        (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-ui-datetime')) {
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

            // External scripts (e.g. event syncDateGuards) may mutate sibling mins/values.
            queueMicrotask(() => {
                document.querySelectorAll('input[data-ui-datetime]').forEach((el) => {
                    if (el instanceof HTMLInputElement && instances.has(el)) {
                        syncInstanceFromInput(el);
                    }
                });
            });
        },
        true,
    );

    document.addEventListener(
        'blur',
        (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-ui-datetime')) {
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

    if (typeof window.Livewire !== 'undefined' && typeof window.Livewire.hook === 'function') {
        window.Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                syncMountedDatetimePickers();
            });
        });
    }
}
