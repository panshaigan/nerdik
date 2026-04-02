import { initTagSelector } from './tags-selector';

/**
 * Run after AJAX-injected slot edit form HTML is inserted into the DOM.
 *
 * @param {ParentNode} root
 */
export function initSlotEditForm(root) {
    if (!root) return;
    if (root.dataset.slotEditFormInitialized) {
        return;
    }
    root.dataset.slotEditFormInitialized = '1';

    const form = root.matches?.('form[data-slot-edit-form]')
        ? root
        : root.querySelector('form[data-slot-edit-form]') || root.closest('form[data-slot-edit-form]');
    if (form) {
        form.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            const t = e.target;
            if (t.tagName === 'TEXTAREA') return;
            if (t.tagName === 'BUTTON') return;
            if (t.tagName === 'INPUT' && (t.type === 'checkbox' || t.type === 'radio' || t.type === 'submit' || t.type === 'button')) return;
            if (t.hasAttribute('data-ts-input')) return;
            if (t.hasAttribute('data-slot-name-input')) return;
            if (t.tagName === 'SELECT' && t.multiple) return;
            if (t.tagName === 'INPUT' || t.tagName === 'SELECT') {
                e.preventDefault();
            }
        });
    }

    root.querySelectorAll('[data-tag-selector]').forEach((el) => {
        if (el.dataset.tsInitialized) return;
        initTagSelector(el);
    });

    const nameInput = root.querySelector('[data-slot-name-input]');
    const namePopup = root.querySelector('[data-slot-name-popup]');
    const suggestionsJson = root.querySelector('script[type="application/json"][data-slot-name-suggestions-json]');
    if (!nameInput || !namePopup || !suggestionsJson) return;

    let suggestions = [];
    try {
        suggestions = JSON.parse(suggestionsJson.textContent || '[]');
    } catch {
        suggestions = [];
    }

    let shown = [];
    let active = -1;

    function closeNamePopup() {
        namePopup.classList.add('hidden');
        namePopup.innerHTML = '';
        active = -1;
        nameInput.setAttribute('aria-expanded', 'false');
    }

    function openNamePopup() {
        if (shown.length === 0) {
            closeNamePopup();
            return;
        }
        namePopup.classList.remove('hidden');
        nameInput.setAttribute('aria-expanded', 'true');
    }

    function applyActive() {
        [...namePopup.querySelectorAll('[data-suggestion-idx]')].forEach((el, idx) => {
            const isActive = idx === active;
            el.classList.toggle('bg-base-200', isActive);
            el.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function choose(value) {
        nameInput.value = value;
        closeNamePopup();
    }

    function render(items) {
        shown = items.slice(0, 8);
        namePopup.innerHTML = '';
        active = -1;

        if (shown.length === 0) {
            closeNamePopup();
            return;
        }

        const frag = document.createDocumentFragment();
        shown.forEach((name, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-base-200';
            btn.textContent = name;
            btn.dataset.suggestionIdx = String(idx);
            btn.setAttribute('role', 'option');
            btn.setAttribute('aria-selected', 'false');
            btn.addEventListener('mousedown', (e) => e.preventDefault());
            btn.addEventListener('click', () => choose(name));
            frag.appendChild(btn);
        });
        namePopup.appendChild(frag);
        openNamePopup();
    }

    function updateFromInput() {
        const q = nameInput.value.trim().toLowerCase();
        if (q.length < 1) {
            closeNamePopup();
            return;
        }

        const items = suggestions.filter((s) => s.toLowerCase().includes(q) && s.toLowerCase() !== q);
        render(items);
    }

    nameInput.addEventListener('input', updateFromInput);
    nameInput.addEventListener('focus', updateFromInput);
    nameInput.addEventListener('keydown', (e) => {
        if (namePopup.classList.contains('hidden') || shown.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            active = (active + 1) % shown.length;
            applyActive();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            active = active <= 0 ? shown.length - 1 : active - 1;
            applyActive();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (active >= 0 && active < shown.length) {
                choose(shown[active]);
            }
        } else if (e.key === 'Escape') {
            closeNamePopup();
        }
    });

    const boundary = root.closest('.modal-box') || root;
    boundary.addEventListener('click', (e) => {
        if (!namePopup.contains(e.target) && e.target !== nameInput) {
            closeNamePopup();
        }
    });
}
