import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import '../css/vendor/flatpickr-theme.css';
import { Polish } from 'flatpickr/dist/l10n/pl.js';

/** @type {WeakMap<HTMLElement, import('flatpickr').Instance>} */
const instances = new WeakMap();

/**
 * @returns {import('livewire').Component | null}
 */
function findWire(root) {
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.find !== 'function') {
        return null;
    }

    const host = root.closest('[wire\\:id]');
    if (!host) {
        return null;
    }

    const id = host.getAttribute('wire:id');
    if (!id) {
        return null;
    }

    return window.Livewire.find(id);
}

/**
 * @param {HTMLElement} root
 */
function fromProp(root) {
    return root.dataset.fromProp || 'from_date';
}

/**
 * @param {HTMLElement} root
 */
function toProp(root) {
    return root.dataset.toProp || 'to_date';
}

/**
 * @param {Date} date
 */
function formatYmd(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/**
 * @param {string | undefined | null} str
 * @returns {Date | null}
 */
function parseYmd(str) {
    if (!str || !/^\d{4}-\d{2}-\d{2}$/.test(str)) {
        return null;
    }

    const [y, m, d] = str.split('-').map(Number);
    if (!y || !m || !d) {
        return null;
    }

    return new Date(y, m - 1, d);
}

/**
 * @param {import('livewire').Component | null} wire
 * @param {HTMLElement} root
 * @param {string | null} from
 * @param {string | null} to
 */
function syncWire(wire, root, from, to) {
    if (!wire) {
        return;
    }

    wire.set(fromProp(root), from || '');
    wire.set(toProp(root), to || '');
}

/**
 * @param {HTMLElement} trigger
 * @param {string} from
 * @param {string} to
 */
function updateTriggerActiveState(trigger, from, to) {
    const active = Boolean(from || to);
    trigger.classList.toggle('is-active', active);
    trigger.setAttribute('aria-pressed', active ? 'true' : 'false');
}

/**
 * @param {HTMLInputElement} input
 * @param {string} from
 * @param {string} to
 */
function updateVisibleInputDisplay(input, from, to) {
    if (input.classList.contains('sr-only')) {
        return;
    }

    if (!from && !to) {
        input.value = '';

        return;
    }

    if (from && to && from !== to) {
        input.value = `${from} – ${to}`;

        return;
    }

    input.value = from || to || '';
}

/**
 * @param {HTMLElement} root
 */
function defaultDatesFromRoot(root) {
    /** @type {Date[]} */
    const dates = [];
    const fromDate = parseYmd(root.dataset.fromDate);
    const toDate = parseYmd(root.dataset.toDate);
    if (fromDate) {
        dates.push(fromDate);
    }
    if (toDate) {
        dates.push(toDate);
    }

    return dates;
}

/**
 * @param {HTMLElement} root
 */
function syncPickerFromRoot(root) {
    const fp = instances.get(root);
    if (!fp) {
        return;
    }

    const dates = defaultDatesFromRoot(root);
    fp.setDate(dates.length > 0 ? dates : [], false);

    const from = root.dataset.fromDate ?? '';
    const to = root.dataset.toDate ?? '';
    const trigger = root.querySelector('[data-browse-date-range-trigger]');
    if (trigger instanceof HTMLElement) {
        updateTriggerActiveState(trigger, from, to);
    }

    const input = root.querySelector('[data-browse-date-range-input]');
    if (input instanceof HTMLInputElement) {
        updateVisibleInputDisplay(input, from, to);
    }
}

/**
 * @param {HTMLElement} root
 */
function initBrowseDateRangePicker(root) {
    if (instances.has(root)) {
        syncPickerFromRoot(root);

        return;
    }

    const input = root.querySelector('[data-browse-date-range-input]');
    const trigger = root.querySelector('[data-browse-date-range-trigger]');
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const locale = root.dataset.locale || 'en';
    const defaultDates = defaultDatesFromRoot(root);
    const minDate = root.dataset.minDate || undefined;

    const fp = flatpickr(input, {
        mode: 'range',
        enableTime: false,
        dateFormat: 'Y-m-d',
        allowInput: false,
        defaultDate: defaultDates.length > 0 ? defaultDates : undefined,
        ...(minDate ? { minDate } : {}),
        ...(locale === 'pl' ? { locale: Polish } : {}),
        onChange(selectedDates) {
            const wire = findWire(root);

            if (selectedDates.length === 0) {
                syncWire(wire, root, null, null);
                if (trigger instanceof HTMLElement) {
                    updateTriggerActiveState(trigger, '', '');
                }
                updateVisibleInputDisplay(input, '', '');

                return;
            }

            if (selectedDates.length === 1) {
                const day = formatYmd(selectedDates[0]);
                syncWire(wire, root, day, null);
                if (trigger instanceof HTMLElement) {
                    updateTriggerActiveState(trigger, day, '');
                }
                updateVisibleInputDisplay(input, day, '');

                return;
            }

            const from = formatYmd(selectedDates[0]);
            const to = formatYmd(selectedDates[1]);
            syncWire(wire, root, from, to);
            if (trigger instanceof HTMLElement) {
                updateTriggerActiveState(trigger, from, to);
            }
            updateVisibleInputDisplay(input, from, to);
        },
        onClose(selectedDates) {
            if (selectedDates.length !== 1) {
                return;
            }

            const day = formatYmd(selectedDates[0]);
            const wire = findWire(root);
            syncWire(wire, root, day, day);
            if (trigger instanceof HTMLElement) {
                updateTriggerActiveState(trigger, day, day);
            }
            updateVisibleInputDisplay(input, day, day);
        },
        onReady(_selectedDates, _dateStr, instance) {
            const clearLabel = root.dataset.clearLabel || 'Clear';
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'flatpickr-clear ui-browse-date-range-clear';
            clearBtn.textContent = clearLabel;
            clearBtn.addEventListener('click', (event) => {
                event.preventDefault();
                instance.clear();
                const wire = findWire(root);
                if (wire && typeof wire.call === 'function' && root.dataset.clearMethod) {
                    wire.call(root.dataset.clearMethod);
                } else {
                    syncWire(wire, root, null, null);
                }
                if (trigger instanceof HTMLElement) {
                    updateTriggerActiveState(trigger, '', '');
                }
                updateVisibleInputDisplay(input, '', '');
                instance.close();
            });
            instance.calendarContainer?.appendChild(clearBtn);

            updateVisibleInputDisplay(
                input,
                root.dataset.fromDate ?? '',
                root.dataset.toDate ?? '',
            );
        },
    });

    if (trigger instanceof HTMLElement) {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            fp.open();
        });
        updateTriggerActiveState(trigger, root.dataset.fromDate ?? '', root.dataset.toDate ?? '');
    }

    instances.set(root, fp);
}

export function bootBrowseDateRangePickers() {
    document.querySelectorAll('[data-browse-date-range]').forEach((root) => {
        if (root instanceof HTMLElement) {
            initBrowseDateRangePicker(root);
        }
    });
}

if (typeof window.Livewire !== 'undefined' && typeof window.Livewire.hook === 'function') {
    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            document.querySelectorAll('[data-browse-date-range]').forEach((root) => {
                if (!(root instanceof HTMLElement)) {
                    return;
                }

                if (instances.has(root)) {
                    syncPickerFromRoot(root);
                } else {
                    initBrowseDateRangePicker(root);
                }
            });
        });
    });
}
