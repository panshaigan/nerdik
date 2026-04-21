/**
 * Per-category activity tag picker (Mary-style chips + typeahead).
 * Syncs tag_ids and new_tags on the enclosing Livewire component.
 */

function norm(v) {
    return String(v || '')
        .toLowerCase()
        .trim();
}

function displayLabel(tag, locale) {
    return tag.labels?.[locale] || tag.labels?.en || tag.slug || `#${tag.id}`;
}

function normTagIds(arr) {
    return [...new Set((arr || []).map((x) => Number(x)).filter((n) => n > 0))].sort((a, b) => a - b);
}

function sameTagIds(a, b) {
    const na = normTagIds(a);
    const nb = normTagIds(b);
    return na.length === nb.length && na.every((v, i) => v === nb[i]);
}

function sameNewTagsPayload(a, b) {
    return JSON.stringify(a || []) === JSON.stringify(b || []);
}

function syncLivewireTagState(root, tagIds, newTagsPayload) {
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.find !== 'function') {
        return;
    }
    const host = root.closest('[wire\\:id]');
    if (!host) {
        return;
    }
    const id = host.getAttribute('wire:id');
    if (!id) {
        return;
    }
    const wire = window.Livewire.find(id);
    if (!wire || typeof wire.set !== 'function') {
        return;
    }
    if (typeof wire.get === 'function' || typeof wire.$get === 'function') {
        const get = typeof wire.get === 'function' ? wire.get.bind(wire) : wire.$get.bind(wire);
        if (sameTagIds(tagIds, get('tag_ids')) && sameNewTagsPayload(newTagsPayload, get('new_tags'))) {
            return;
        }
    }
    wire.set('tag_ids', tagIds);
    wire.set('new_tags', newTagsPayload);
}

function getActivityTypeIdFromLivewire(root) {
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.find !== 'function') {
        return null;
    }
    const host = root.closest('[wire\\:id]');
    const wireId = host?.getAttribute('wire:id');
    const wire = wireId ? window.Livewire.find(wireId) : null;
    if (!wire) {
        return null;
    }
    const get = typeof wire.get === 'function' ? wire.get.bind(wire) : typeof wire.$get === 'function' ? wire.$get.bind(wire) : null;
    if (!get) {
        return null;
    }
    const raw = get('activity_type_id');
    const n = Number(raw);
    return n > 0 ? n : null;
}

function isSuggestible(tag, activityTypeId) {
    const ctx = tag.context_activity_type_ids;
    if (!Array.isArray(ctx) || ctx.length === 0) {
        return true;
    }
    if (activityTypeId === null || activityTypeId <= 0) {
        return false;
    }
    return ctx.map(Number).includes(activityTypeId);
}

export function initActivityTagPicker(root) {
    if (!root || root.dataset.atpInitialized === '1') {
        return;
    }

    const MAX_RESULTS = 18;

    const cfgEl = root.querySelector('script[type="application/json"][data-atp-config]');
    if (!cfgEl) {
        return;
    }

    let cfg = {};
    try {
        cfg = JSON.parse(cfgEl.textContent || '{}');
    } catch {
        cfg = {};
    }

    const locale = cfg.locale || 'en';
    const allTags = Array.isArray(cfg.tags) ? cfg.tags : [];
    const byId = new Map(allTags.map((t) => [Number(t.id), t]));
    const selected = new Set((cfg.initialSelectedIds || []).map((x) => Number(x)));
    const explicitSelected = new Set((cfg.initialSelectedIds || []).map((x) => Number(x)));
    const autoSelected = new Set();
    let pendingNew = Array.isArray(cfg.initialNewTags) ? [...cfg.initialNewTags] : [];

    const rows = [...root.querySelectorAll('[data-atp-category-row]')];
    if (rows.length === 0) {
        return;
    }

    /** @type {Map<number, { input: HTMLInputElement, results: HTMLElement, chips: HTMLElement, activeIndex: number }>} */
    const rowUi = new Map();

    rows.forEach((rowEl) => {
        const catId = Number(rowEl.dataset.categoryId);
        const input = rowEl.querySelector('[data-atp-input]');
        const results = rowEl.querySelector('[data-atp-results]');
        const chips = rowEl.querySelector('[data-atp-chips]');
        if (!input || !results || !chips || !catId) {
            return;
        }
        rowUi.set(catId, { input, results, chips, activeIndex: -1 });
    });

    function emitTagsChange() {
        const tagIds = Array.from(selected)
            .sort((a, b) => a - b)
            .map((id) => Number(id));
        const newTagsPayload = pendingNew.map((t) => ({
            label: t.label,
            category_id: Number(t.category_id),
        }));
        syncLivewireTagState(root, tagIds, newTagsPayload);
        root.dispatchEvent(
            new CustomEvent('activity-tags-changed', {
                bubbles: true,
                detail: { tagIds, newTags: newTagsPayload },
            })
        );
    }

    function clearRowStack(catId) {
        const ui = rowUi.get(catId);
        const row = ui?.input.closest('[data-atp-category-row]');
        if (row) {
            row.style.zIndex = '';
        }
    }

    /** Later category rows share z-index and paint over earlier dropdowns; raise the open row. */
    function prepareOpenDropdown(catId) {
        rowUi.forEach((_, id) => {
            if (id !== catId) {
                closeResults(id);
            }
        });
        const row = rowUi.get(catId)?.input.closest('[data-atp-category-row]');
        if (row) {
            row.style.zIndex = '2000';
        }
    }

    function closeResults(catId) {
        const ui = rowUi.get(catId);
        if (!ui) {
            return;
        }
        ui.results.classList.add('hidden');
        ui.activeIndex = -1;
        clearRowStack(catId);
    }

    function resultButtons(catId) {
        const ui = rowUi.get(catId);
        if (!ui) {
            return [];
        }
        return Array.from(ui.results.querySelectorAll('button[data-atp-item="1"]'));
    }

    function paintActive(catId) {
        const ui = rowUi.get(catId);
        if (!ui) {
            return;
        }
        const items = resultButtons(catId);
        items.forEach((el, idx) => el.classList.toggle('bg-base-200', idx === ui.activeIndex));
        if (ui.activeIndex >= 0) {
            items[ui.activeIndex]?.scrollIntoView({ block: 'nearest' });
        }
    }

    function removeTag(id) {
        const n = Number(id);
        selected.delete(n);
        explicitSelected.delete(n);
        autoSelected.delete(n);
        renderAllRows();
    }

    function removePending(catId, labelNorm) {
        pendingNew = pendingNew.filter((t) => !(Number(t.category_id) === catId && norm(t.label) === labelNorm));
        renderAllRows();
    }

    function addWithAttached(id) {
        const rootId = Number(id);
        const stack = [{ id: rootId, isAuto: false }];
        while (stack.length) {
            const curMeta = stack.pop();
            const cur = curMeta?.id;
            if (!cur || selected.has(cur)) {
                continue;
            }
            selected.add(cur);
            if (curMeta?.isAuto) {
                autoSelected.add(cur);
            } else {
                explicitSelected.add(cur);
                autoSelected.delete(cur);
            }
            const t = byId.get(cur);
            (t?.related_ids || []).forEach((aid) => {
                if (!selected.has(Number(aid))) {
                    stack.push({ id: Number(aid), isAuto: true });
                }
            });
        }
        renderAllRows();
    }

    function addNewTagLabel(label, categoryId) {
        const l = label.trim();
        if (!l) {
            return;
        }
        const cid = Number(categoryId);
        if (pendingNew.some((t) => norm(t.label) === norm(l) && Number(t.category_id) === cid)) {
            return;
        }
        pendingNew.push({ label: l, category_id: cid });
        renderAllRows();
    }

    function exactTagForQuery(q, categoryId) {
        const qn = norm(q);
        if (!qn) {
            return null;
        }
        const activityTypeId = getActivityTypeIdFromLivewire(root);
        return (
            allTags.find((t) => {
                if (Number(t.category_id) !== Number(categoryId)) {
                    return false;
                }
                if (!isSuggestible(t, activityTypeId)) {
                    return false;
                }
                const labels = Object.values(t.labels || {}).map(norm);
                const aliases = (t.aliases || []).map(norm);
                const hay = [...labels, ...aliases, norm(t.slug)];
                return hay.includes(qn);
            }) || null
        );
    }

    function tagsForCategoryRow(categoryId, q) {
        const activityTypeId = getActivityTypeIdFromLivewire(root);
        const qn = norm(q);
        let pool = allTags.filter((t) => Number(t.category_id) === Number(categoryId) && isSuggestible(t, activityTypeId));

        if (!qn) {
            return pool.slice(0, MAX_RESULTS);
        }
        return pool
            .map((tag) => {
                const labels = Object.values(tag.labels || {}).map(norm);
                const aliases = (tag.aliases || []).map((a) => norm(a));
                const hay = [...labels, ...aliases, norm(tag.slug)];
                const matched = hay.some((h) => h.includes(qn));
                return matched ? tag : null;
            })
            .filter(Boolean)
            .slice(0, MAX_RESULTS);
    }

    function buildResults(catId, q) {
        const ui = rowUi.get(catId);
        if (!ui) {
            return;
        }
        const { results } = ui;
        results.innerHTML = '';
        ui.activeIndex = -1;

        const found = tagsForCategoryRow(catId, q);
        if (!found.length) {
            const qn = norm(q);
            if (qn) {
                const make = document.createElement('button');
                make.type = 'button';
                make.dataset.atpItem = '1';
                make.className =
                    'block w-full px-3 py-2 text-left text-sm font-medium text-primary hover:bg-base-200';
                make.textContent = `+ ${cfg.strings?.createTag || 'Create tag'}: "${q.trim()}"`;
                make.addEventListener('click', () => {
                    addNewTagLabel(q.trim(), catId);
                    ui.input.value = '';
                    closeResults(catId);
                });
                results.appendChild(make);
                prepareOpenDropdown(catId);
                results.classList.remove('hidden');
            } else {
                closeResults(catId);
            }
            return;
        }

        const qn = norm(q);
        const exact = qn
            ? found.some((t) =>
                  [norm(displayLabel(t, locale)), norm(t.labels?.en || ''), ...(t.aliases || []).map(norm)].includes(qn)
              )
            : false;

        const frag = document.createDocumentFragment();
        found.forEach((t) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.dataset.atpItem = '1';
            b.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-base-200';
            const local = displayLabel(t, locale);
            const en = t.labels?.en && norm(t.labels.en) !== norm(local) ? ` / ${t.labels.en}` : '';
            b.textContent = `${local}${en}`;
            b.addEventListener('click', () => {
                addWithAttached(t.id);
                ui.input.value = '';
                closeResults(catId);
            });
            frag.appendChild(b);
        });

        if (qn && !exact) {
            const make = document.createElement('button');
            make.type = 'button';
            make.dataset.atpItem = '1';
            make.className =
                'block w-full border-t border-base-300 px-3 py-2 text-left text-sm font-medium text-primary hover:bg-base-200';
            make.textContent = `+ ${cfg.strings?.createTag || 'Create tag'}: "${q.trim()}"`;
            make.addEventListener('click', () => {
                addNewTagLabel(q.trim(), catId);
                ui.input.value = '';
                closeResults(catId);
            });
            frag.appendChild(make);
        }

        results.appendChild(frag);
        prepareOpenDropdown(catId);
        results.classList.remove('hidden');
    }

    function renderRowChips(catId) {
        const ui = rowUi.get(catId);
        if (!ui) {
            return;
        }
        const { chips } = ui;
        chips.innerHTML = '';

        Array.from(selected)
            .sort((a, b) => a - b)
            .forEach((id) => {
                const t = byId.get(id);
                if (!t || Number(t.category_id) !== Number(catId)) {
                    return;
                }
                const label = displayLabel(t, locale);
                const isAuto = autoSelected.has(id) && !explicitSelected.has(id);
                const autoBadge = isAuto
                    ? ` <span class="rounded bg-base-300 px-1 py-0.5 text-[10px] uppercase">${cfg.strings?.auto || 'auto'}</span>`
                    : '';
                const chip = document.createElement('span');
                chip.className =
                    'mary-tags-element inline-flex items-center gap-1 rounded-full border border-base-300 bg-base-200 px-3 py-1 text-xs';
                chip.innerHTML = `${label}${autoBadge} <button type="button" class="opacity-60 hover:opacity-100" data-rm="${id}">×</button>`;
                chip.querySelector('button')?.addEventListener('click', () => removeTag(id));
                chips.appendChild(chip);
            });

        pendingNew
            .filter((t) => Number(t.category_id) === Number(catId))
            .forEach((t) => {
                const chip = document.createElement('span');
                chip.className =
                    'mary-tags-element inline-flex items-center gap-1 rounded-full border border-primary/40 bg-primary/10 px-3 py-1 text-xs';
                chip.innerHTML = `${t.label} <button type="button" class="opacity-60 hover:opacity-100" data-rm-pending="${norm(t.label)}">×</button>`;
                chip.querySelector('button')?.addEventListener('click', () => removePending(catId, norm(t.label)));
                chips.appendChild(chip);
            });
    }

    function renderAllRows() {
        rowUi.forEach((_, catId) => renderRowChips(catId));
        emitTagsChange();
    }

    rowUi.forEach((ui, catId) => {
        const { input } = ui;
        input.addEventListener('input', () => buildResults(catId, input.value));
        input.addEventListener('focus', () => buildResults(catId, input.value));
        input.addEventListener('keydown', (e) => {
            const items = resultButtons(catId);
            const dropdownOpen = !ui.results.classList.contains('hidden') && items.length > 0;

            if (e.key === 'ArrowDown') {
                if (!dropdownOpen) {
                    return;
                }
                e.preventDefault();
                ui.activeIndex = (ui.activeIndex + 1 + items.length) % items.length;
                paintActive(catId);
                return;
            }
            if (e.key === 'ArrowUp') {
                if (!dropdownOpen) {
                    return;
                }
                e.preventDefault();
                ui.activeIndex = (ui.activeIndex - 1 + items.length) % items.length;
                paintActive(catId);
                return;
            }
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const q = input.value.replace(/,/g, '').trim();
                if (!q) {
                    closeResults(catId);
                    return;
                }
                if (dropdownOpen && ui.activeIndex >= 0) {
                    items[ui.activeIndex]?.click();
                    return;
                }
                const exact = exactTagForQuery(q, catId);
                if (exact) {
                    addWithAttached(exact.id);
                    input.value = '';
                    closeResults(catId);
                    return;
                }
                addNewTagLabel(q, catId);
                input.value = '';
                closeResults(catId);
                return;
            }
            if (e.key === 'Escape') {
                closeResults(catId);
            }
        });
    });

    document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) {
            rowUi.forEach((_, catId) => closeResults(catId));
        }
    });

    renderAllRows();
    root.dataset.atpInitialized = '1';
}

export function bootActivityTagPickers(container = document) {
    container.querySelectorAll('[data-activity-tag-picker]').forEach((el) => {
        initActivityTagPicker(el);
    });
}
