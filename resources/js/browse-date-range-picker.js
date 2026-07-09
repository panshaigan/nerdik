import flatpickr from 'flatpickr';
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
 * @param {Date} date
 */
function formatYmd(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/**
 * @param {string | undefined | null}
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
 */
function syncWire(wire, from, to) {
    if (!wire) {
        return;
    }

    wire.set('from_date', from || null);
    wire.set('to_date', to || null);
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

    const trigger = root.querySelector('[data-browse-date-range-trigger]');
    if (trigger instanceof HTMLElement) {
        updateTriggerActiveState(trigger, root.dataset.fromDate ?? '', root.dataset.toDate ?? '');
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
    if (!(input instanceof HTMLInputElement) || !(trigger instanceof HTMLElement)) {
        return;
    }

    const locale = root.dataset.locale || 'en';
    const defaultDates = defaultDatesFromRoot(root);

    const fp = flatpickr(input, {
        mode: 'range',
        enableTime: false,
        dateFormat: 'Y-m-d',
        defaultDate: defaultDates.length > 0 ? defaultDates : undefined,
        ...(locale === 'pl' ? { locale: Polish } : {}),
        onChange(selectedDates) {
            const wire = findWire(root);

            if (selectedDates.length === 0) {
                syncWire(wire, null, null);
                updateTriggerActiveState(trigger, '', '');

                return;
            }

            if (selectedDates.length === 1) {
                const day = formatYmd(selectedDates[0]);
                syncWire(wire, day, null);
                updateTriggerActiveState(trigger, day, '');

                return;
            }

            const from = formatYmd(selectedDates[0]);
            const to = formatYmd(selectedDates[1]);
            syncWire(wire, from, to);
            updateTriggerActiveState(trigger, from, to);
        },
        onClose(selectedDates) {
            if (selectedDates.length !== 1) {
                return;
            }

            const day = formatYmd(selectedDates[0]);
            const wire = findWire(root);
            syncWire(wire, day, day);
            updateTriggerActiveState(trigger, day, day);
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
                if (wire && typeof wire.call === 'function') {
                    wire.call('clearDateRange');
                } else {
                    syncWire(wire, null, null);
                }
                updateTriggerActiveState(trigger, '', '');
                instance.close();
            });
            instance.calendarContainer?.appendChild(clearBtn);
        },
    });

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        fp.open();
    });

    instances.set(root, fp);
    updateTriggerActiveState(trigger, root.dataset.fromDate ?? '', root.dataset.toDate ?? '');
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
                if (root instanceof HTMLElement) {
                    syncPickerFromRoot(root);
                }
            });
        });
    });
}
