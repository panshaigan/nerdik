function norm(v) {
    return String(v || '').toLowerCase().trim();
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

function sortByPopularity(tags) {
    return [...tags].sort((a, b) => {
        const scoreDiff = (Number(b.popularity_score) || 0) - (Number(a.popularity_score) || 0);
        if (scoreDiff !== 0) {
            return scoreDiff;
        }
        return Number(a.id) - Number(b.id);
    });
}

function tagCategoryKey(tag) {
    return String(tag?.category_key || '').trim();
}

function orderedCategoryKeysForDisplay(categoryOrder, groupedKeys) {
    const seen = new Set();
    const ordered = [];
    (categoryOrder || []).forEach((key) => {
        const normalized = String(key || '').trim();
        if (!normalized || seen.has(normalized) || !groupedKeys.has(normalized)) {
            return;
        }
        seen.add(normalized);
        ordered.push(normalized);
    });
    [...groupedKeys].sort().forEach((key) => {
        if (!seen.has(key)) {
            ordered.push(key);
        }
    });
    return ordered;
}

function fillTagsByCategoryOrder(pool, categoryOrder, maxResults) {
    const byKey = new Map();
    pool.forEach((tag) => {
        const key = tagCategoryKey(tag) || 'other';
        if (!byKey.has(key)) {
            byKey.set(key, []);
        }
        byKey.get(key).push(tag);
    });
    byKey.forEach((tags, key) => {
        byKey.set(key, sortByPopularity(tags));
    });

    const keys = orderedCategoryKeysForDisplay(categoryOrder, new Set(byKey.keys()));
    const result = [];
    for (const key of keys) {
        const tags = byKey.get(key) || [];
        for (const tag of tags) {
            if (result.length >= maxResults) {
                return result;
            }
            result.push(tag);
        }
    }
    return result;
}

/**
 * Sync tag state into the nearest Livewire component that wraps this selector.
 * Window-level Alpine listeners using $wire can attach to the wrong component when multiple Livewire roots exist (e.g. nav + form).
 */
function syncLivewireTagState(root, tagIds, newTagsPayload) {
    if (root.dataset.tsSkipLivewireSync === '1') {
        return;
    }

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

    if (root.hasAttribute('data-browse-tag-selector') && typeof wire.call === 'function') {
        wire.call('syncBrowseTagsFromSelector', tagIds, newTagsPayload);
        return;
    }

    wire.set('tag_ids', tagIds);
    wire.set('new_tags', newTagsPayload);
}

export function initTagSelector(root) {
    if (root.dataset.tsInitialized) return;

    const MAX_SUGGESTIONS = 8;
    const MAX_RESULTS = 18;

    const cfgEl = root.querySelector('script[type="application/json"][data-ts-config]');
    const input = root.querySelector('[data-ts-input]');
    const results = root.querySelector('[data-ts-results]');
    const chips = root.querySelector('[data-ts-chips]');
    const hiddenIds = root.querySelector('[data-ts-hidden-ids]');
    const newTagsWrap = root.querySelector('[data-ts-new-wrap]');
    const newTags = root.querySelector('[data-ts-new]');
    if (!cfgEl || !input || !results || !chips || !hiddenIds || !newTagsWrap || !newTags) return;

    let cfg = {};
    try {
        cfg = JSON.parse(cfgEl.textContent || '{}');
    } catch {
        cfg = {};
    }

    const locale = cfg.locale || 'en';
    const allowCreate = cfg.allowCreate !== false;
    const allTags = Array.isArray(cfg.tags) ? cfg.tags : [];
    const categories = Array.isArray(cfg.categories) ? cfg.categories : [];
    const categoryNameById = new Map(categories.map((c) => [Number(c.id), String(c.name || '')]));
    const categoryNameByKey = new Map(categories.map((c) => [String(c.key || ''), String(c.name || '')]));
    const browseSuggestions = cfg.browseSuggestions || null;
    const browseCategoryOrder = Array.isArray(browseSuggestions?.categoryOrder) ? browseSuggestions.categoryOrder : [];
    const browseHiddenOnEmpty = new Set(
        Array.isArray(browseSuggestions?.hiddenCategoryKeysOnEmptySearch)
            ? browseSuggestions.hiddenCategoryKeysOnEmptySearch.map((k) => String(k || '').trim()).filter(Boolean)
            : []
    );
    const byId = new Map(allTags.map((t) => [Number(t.id), t]));
    const selected = new Set((cfg.initialSelectedIds || []).map((x) => Number(x)));
    const explicitSelected = new Set((cfg.initialSelectedIds || []).map((x) => Number(x)));
    const autoSelected = new Set();
    let pendingNew = Array.isArray(cfg.initialNewTags) ? [...cfg.initialNewTags] : [];
    let activeIndex = -1;

    const browseTextSearch = cfg.browseTextSearch;

    let browseTextQuery = String(browseTextSearch?.value || '').trim();

    function renderBrowseTextChip() {
        if (!browseTextSearch?.enabled) {
            return;
        }

        chips.querySelectorAll('[data-ts-browse-text-chip="1"]').forEach((el) => el.remove());
        if (!browseTextQuery) {
            return;
        }

        const chip = document.createElement('span');
        chip.dataset.tsBrowseTextChip = '1';
        chip.className =
            'inline-flex max-w-full items-center gap-1 rounded-full border border-secondary/40 bg-secondary/10 px-3 py-1 mt-2 text-xs text-base-content';
        chip.title = cfg.strings?.browseTextSearchHint || 'Text search';

        const label = document.createElement('span');
        label.className = 'sr-only';
        label.textContent = `${cfg.strings?.browseTextSearchLabel || 'Text search'}:`;

        const value = document.createElement('span');
        value.className = 'min-w-0 truncate';
        value.textContent = browseTextQuery;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'opacity-60 hover:opacity-100';
        remove.setAttribute('aria-label', cfg.strings?.browseTextSearchRemove || 'Remove text search');
        remove.textContent = '×';
        remove.addEventListener('click', () => {
            setBrowseTextQuery('');
            renderBrowseTextChip();
        });

        chip.append(label, value, remove);
        chips.appendChild(chip);
    }

    /** Browse only: set Livewire name/description query (`q`) and keep inline text chip in sync. */
    function setBrowseTextQuery(raw) {
        if (!browseTextSearch?.enabled) {
            return;
        }
        const prop = browseTextSearch.property || 'q';
        if (typeof window.Livewire === 'undefined' || typeof window.Livewire.find !== 'function') {
            return;
        }
        const host = root.closest('[wire\\:id]');
        const id = host?.getAttribute('wire:id');
        const wire = id ? window.Livewire.find(id) : null;
        if (!wire || typeof wire.set !== 'function') {
            return;
        }
        const get =
            typeof wire.get === 'function' ? wire.get.bind(wire) : typeof wire.$get === 'function' ? wire.$get.bind(wire) : null;
        const val = String(raw ?? '').trim();
        browseTextQuery = val;
        if (get && get(prop) === val) {
            renderBrowseTextChip();
            return;
        }
        wire.set(prop, val);
        renderBrowseTextChip();
    }

    /** Clear typing field only (does not change committed text search `q`). */
    function clearTagSearchInput() {
        input.value = '';
    }

    if (browseTextSearch?.enabled) {
        input.value = '';
    }

    function emitTagsChange() {
        const tagIds = Array.from(selected)
            .sort((a, b) => a - b)
            .map((id) => Number(id));
        const newTagsPayload = pendingNew.map((t) => ({
            label: t.label,
            category_id: Number(t.category_id),
        }));

        if (!root.hasAttribute('data-browse-tag-selector')) {
            if (typeof window.Livewire !== 'undefined' && typeof window.Livewire.find === 'function') {
                const host = root.closest('[wire\\:id]');
                const id = host?.getAttribute('wire:id');
                const wire = id ? window.Livewire.find(id) : null;
                const get = wire && (typeof wire.get === 'function' ? wire.get.bind(wire) : typeof wire.$get === 'function' ? wire.$get.bind(wire) : null);
                if (get && sameTagIds(tagIds, get('tag_ids')) && sameNewTagsPayload(newTagsPayload, get('new_tags'))) {
                    return;
                }
            }
        }

        syncLivewireTagState(root, tagIds, newTagsPayload);
        root.dispatchEvent(
            new CustomEvent('tags-changed', {
                bubbles: true,
                detail: { tagIds, newTags: newTagsPayload },
            })
        );
    }

    function updateHiddenInputs() {
        hiddenIds.innerHTML = '';
        Array.from(selected)
            .sort((a, b) => a - b)
            .forEach((id) => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'tag_ids[]';
                inp.value = String(id);
                hiddenIds.appendChild(inp);
            });
    }

    function renderPendingNew() {
        newTags.innerHTML = '';
        pendingNew.forEach((t, idx) => {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 rounded-md border border-base-300 bg-base-100 px-2 py-1';

            const name = document.createElement('span');
            name.className = 'text-sm';
            name.textContent = t.label;

            const sel = document.createElement('select');
            sel.className = 'select select-bordered select-xs';
            categories.forEach((cat) => {
                const opt = document.createElement('option');
                opt.value = String(cat.id);
                opt.textContent = cat.name;
                opt.selected = Number(t.category_id) === Number(cat.id);
                sel.appendChild(opt);
            });
            sel.addEventListener('change', () => {
                pendingNew[idx].category_id = Number(sel.value);
                renderPendingNew();
            });

            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'btn btn-ghost btn-xs';
            rm.textContent = '×';
            rm.addEventListener('click', () => {
                pendingNew = pendingNew.filter((_, i) => i !== idx);
                renderPendingNew();
            });

            row.append(name, sel, rm);

            const hLabel = document.createElement('input');
            hLabel.type = 'hidden';
            hLabel.name = `new_tags[${idx}][label]`;
            hLabel.value = t.label;

            const hCategory = document.createElement('input');
            hCategory.type = 'hidden';
            hCategory.name = `new_tags[${idx}][category_id]`;
            hCategory.value = String(Number(t.category_id));

            row.append(hLabel, hCategory);

            newTags.appendChild(row);
        });

        newTagsWrap.classList.toggle('hidden', pendingNew.length === 0);
        emitTagsChange();
    }

    function removeTag(id) {
        const n = Number(id);
        selected.delete(n);
        explicitSelected.delete(n);
        autoSelected.delete(n);
        renderSelected();
    }

    function addWithAttached(id) {
        const rootId = Number(id);
        if (root.hasAttribute('data-browse-tag-selector')) {
            if (!rootId || selected.has(rootId)) return;
            selected.add(rootId);
            explicitSelected.add(rootId);
            autoSelected.delete(rootId);
            renderSelected();
            return;
        }

        const stack = [{ id: rootId, isAuto: false }];
        while (stack.length) {
            const curMeta = stack.pop();
            const cur = curMeta?.id;
            if (!cur || selected.has(cur)) continue;
            selected.add(cur);
            if (curMeta?.isAuto) {
                autoSelected.add(cur);
            } else {
                explicitSelected.add(cur);
                autoSelected.delete(cur);
            }
            const t = byId.get(cur);
            (t?.related_ids || []).forEach((aid) => {
                if (!selected.has(Number(aid))) stack.push({ id: Number(aid), isAuto: true });
            });
        }
        renderSelected();
    }

    function renderSelected() {
        chips.innerHTML = '';
        Array.from(selected)
            .sort((a, b) => a - b)
            .forEach((id) => {
                const t = byId.get(id);
                const label = t ? displayLabel(t, locale) : `#${id}`;
                const catName = t?.category_name || categoryNameById.get(Number(t?.category_id || 0)) || '';
                const cat = catName ? ` (${catName})` : '';
                const isAuto = autoSelected.has(id) && !explicitSelected.has(id);
                const autoBadge = isAuto
                    ? ` <span class="rounded bg-base-300 px-1 py-0.5 text-[10px] uppercase">${cfg.strings?.auto || 'auto'}</span>`
                    : '';
                const chip = document.createElement('span');
                chip.className = 'inline-flex items-center gap-1 rounded-full border border-base-300 bg-base-200 px-3 py-1 mt-2 text-xs';
                chip.innerHTML = `${label}${cat}${autoBadge} <button type="button" class="opacity-60 hover:opacity-100" data-rm="${id}">×</button>`;
                chip.querySelector('button')?.addEventListener('click', () => removeTag(id));
                chips.appendChild(chip);
            });
        renderBrowseTextChip();
        updateHiddenInputs();
        emitTagsChange();
    }

    function closeResults() {
        results.classList.add('hidden');
        activeIndex = -1;
    }

    function resultButtons() {
        return Array.from(results.querySelectorAll('button[data-ts-item="1"]'));
    }

    function paintActive() {
        const items = resultButtons();
        items.forEach((el, idx) => el.classList.toggle('bg-base-200', idx === activeIndex));
        if (activeIndex >= 0) items[activeIndex]?.scrollIntoView({ block: 'nearest' });
    }

    function addNewTagLabel(label) {
        const l = label.trim();
        if (!l) return;
        if (pendingNew.some((t) => norm(t.label) === norm(l))) return;
        pendingNew.push({ label: l, category_id: Number(categories[0]?.id || 0) });
        renderPendingNew();
    }

    /** Returns the tag object if the query matches an existing tag label/slug/alias exactly. */
    function exactTagForQuery(q) {
        const qn = norm(q);
        if (!qn) return null;
        return (
            allTags.find((t) => {
                const labels = Object.values(t.labels || {}).map(norm);
                const aliases = (t.aliases || []).map(norm);
                const hay = [...labels, ...aliases, norm(t.slug)];
                return hay.includes(qn);
            }) || null
        );
    }

    function buildResults(q) {
        results.innerHTML = '';
        activeIndex = -1;
        const qn = norm(q);

        let pool = allTags;
        if (!qn && browseHiddenOnEmpty.size > 0) {
            pool = pool.filter((tag) => !browseHiddenOnEmpty.has(tagCategoryKey(tag)));
        }

        let found;
        if (!qn) {
            found = fillTagsByCategoryOrder(pool, browseCategoryOrder, MAX_RESULTS);
        } else {
            found = fillTagsByCategoryOrder(
                sortByPopularity(
                    pool
                        .map((tag) => {
                            const labels = Object.values(tag.labels || {}).map(norm);
                            const aliases = (tag.aliases || []).map((a) => norm(a));
                            const hay = [...labels, ...aliases, norm(tag.slug)];
                            const matched = hay.some((h) => h.includes(qn));
                            return matched ? tag : null;
                        })
                        .filter(Boolean)
                ),
                browseCategoryOrder,
                MAX_RESULTS
            );
        }

        if (!found.length) {
            closeResults();
            return;
        }

        const grouped = new Map();
        found.forEach((tag) => {
            const key = tagCategoryKey(tag) || 'other';
            if (!grouped.has(key)) {
                const displayName =
                    tag.category_name ||
                    categoryNameByKey.get(key) ||
                    categoryNameById.get(Number(tag.category_id || 0)) ||
                    key;
                grouped.set(key, { displayName, tags: [] });
            }
            grouped.get(key).tags.push(tag);
        });

        const exact = qn
            ? found.some((t) =>
                  [norm(displayLabel(t, locale)), norm(t.labels?.en || ''), ...(t.aliases || []).map(norm)].includes(
                      qn
                  )
              )
            : false;

        const frag = document.createDocumentFragment();
        const groupKeys =
            browseCategoryOrder.length > 0
                ? orderedCategoryKeysForDisplay(browseCategoryOrder, new Set(grouped.keys()))
                : [...grouped.keys()];

        groupKeys.forEach((key) => {
            const group = grouped.get(key);
            if (!group) {
                return;
            }
            const head = document.createElement('div');
            head.className = 'px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-base-content/50';
            head.textContent = group.displayName;
            frag.appendChild(head);
            group.tags.forEach((t) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.dataset.tsItem = '1';
                b.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-base-200';
                const local = displayLabel(t, locale);
                const en = t.labels?.en && norm(t.labels.en) !== norm(local) ? ` / ${t.labels.en}` : '';
                b.textContent = `${local}${en}`;
                b.addEventListener('click', () => {
                    addWithAttached(t.id);
                    clearTagSearchInput();
                    closeResults();
                });
                frag.appendChild(b);
            });
        });

        if (allowCreate && qn && !exact) {
            const make = document.createElement('button');
            make.type = 'button';
            make.dataset.tsItem = '1';
            make.className = 'block w-full border-t border-base-300 px-3 py-2 text-left text-sm font-medium text-primary hover:bg-base-200';
            make.textContent = `+ ${cfg.strings?.createTag || 'Create tag'}: "${q.trim()}"`;
            make.addEventListener('click', () => {
                addNewTagLabel(q.trim());
                clearTagSearchInput();
                closeResults();
            });
            frag.appendChild(make);
        }

        results.appendChild(frag);
        results.classList.remove('hidden');
    }

    const fieldShell = input.closest('[data-ts-field]');
    if (fieldShell && fieldShell.dataset.tsShellClickBound !== '1') {
        fieldShell.dataset.tsShellClickBound = '1';
        fieldShell.addEventListener('click', (e) => {
            const t = e.target;
            if (t === input) {
                return;
            }
            if (t.closest?.('button')) {
                return;
            }
            input.focus();
            buildResults(input.value);
        });
    }

    input.addEventListener('input', () => {
        buildResults(input.value);
    });
    input.addEventListener('focus', () => buildResults(input.value));
    input.addEventListener('keydown', (e) => {
        const items = resultButtons();
        const dropdownOpen = !results.classList.contains('hidden') && items.length > 0;

        if (e.key === 'ArrowDown') {
            if (!dropdownOpen) return;
            e.preventDefault();
            activeIndex = (activeIndex + 1 + items.length) % items.length;
            paintActive();
            return;
        }
        if (e.key === 'ArrowUp') {
            if (!dropdownOpen) return;
            e.preventDefault();
            activeIndex = (activeIndex - 1 + items.length) % items.length;
            paintActive();
            return;
        }
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const q = input.value.replace(/,/g, '').trim();
            if (!q) {
                closeResults();
                setBrowseTextQuery('');
                return;
            }
            if (dropdownOpen && activeIndex >= 0) {
                items[activeIndex]?.click();
                return;
            }
            const exact = exactTagForQuery(q);
            if (exact) {
                addWithAttached(exact.id);
                clearTagSearchInput();
                closeResults();
                return;
            }
            if (!allowCreate) {
                closeResults();
                setBrowseTextQuery(q);
                clearTagSearchInput();
                return;
            }
            addNewTagLabel(q);
            clearTagSearchInput();
            closeResults();
            return;
        }
        if (e.key === 'Escape') {
            closeResults();
        }
    });

    document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) closeResults();
    });

    renderSelected();
    renderPendingNew();
    root.dataset.tsInitialized = '1';
}

