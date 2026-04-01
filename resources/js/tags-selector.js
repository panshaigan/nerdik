function norm(v) {
    return String(v || '').toLowerCase().trim();
}

function displayLabel(tag, locale) {
    return tag.labels?.[locale] || tag.labels?.en || tag.slug || `#${tag.id}`;
}

export function initTagSelector(root) {
    if (root.dataset.tsInitialized) return;

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
    const allTags = Array.isArray(cfg.tags) ? cfg.tags : [];
    const categories = Array.isArray(cfg.categories) ? cfg.categories : [];
    const byId = new Map(allTags.map((t) => [Number(t.id), t]));
    const selected = new Set((cfg.initialSelectedIds || []).map((x) => Number(x)));
    const explicitSelected = new Set((cfg.initialSelectedIds || []).map((x) => Number(x)));
    const autoSelected = new Set();
    let pendingNew = Array.isArray(cfg.initialNewTags) ? [...cfg.initialNewTags] : [];
    let activeIndex = -1;

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
                opt.value = cat;
                opt.textContent = cat;
                opt.selected = t.category === cat;
                sel.appendChild(opt);
            });
            sel.addEventListener('change', () => {
                pendingNew[idx].category = sel.value;
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
            hCategory.name = `new_tags[${idx}][category]`;
            hCategory.value = t.category;

            row.append(hLabel, hCategory);

            newTags.appendChild(row);
        });

        newTagsWrap.classList.toggle('hidden', pendingNew.length === 0);
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
            (t?.attached_ids || []).forEach((aid) => {
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
                const cat = t?.category ? ` (${t.category})` : '';
                const isAuto = autoSelected.has(id) && !explicitSelected.has(id);
                const autoBadge = isAuto
                    ? ` <span class="rounded bg-base-300 px-1 py-0.5 text-[10px] uppercase">${cfg.strings?.auto || 'auto'}</span>`
                    : '';
                const chip = document.createElement('span');
                chip.className = 'inline-flex items-center gap-1 rounded-full border border-base-300 bg-base-200 px-3 py-1 text-xs';
                chip.innerHTML = `${label}${cat}${autoBadge} <button type="button" class="opacity-60 hover:opacity-100" data-rm="${id}">×</button>`;
                chip.querySelector('button')?.addEventListener('click', () => removeTag(id));
                chips.appendChild(chip);
            });
        updateHiddenInputs();
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
        pendingNew.push({ label: l, category: categories[0] || 'game' });
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
        if (!qn) {
            closeResults();
            return;
        }

        const found = allTags
            .map((tag) => {
                const labels = Object.values(tag.labels || {}).map(norm);
                const aliases = (tag.aliases || []).map((a) => norm(a));
                const hay = [...labels, ...aliases, norm(tag.slug)];
                const matched = hay.some((h) => h.includes(qn));
                return matched ? tag : null;
            })
            .filter(Boolean)
            .slice(0, 18);

        const grouped = new Map();
        found.forEach((tag) => {
            const cat = tag.category || 'other';
            if (!grouped.has(cat)) grouped.set(cat, []);
            grouped.get(cat).push(tag);
        });

        const exact = found.some((t) =>
            [norm(displayLabel(t, locale)), norm(t.labels?.en || ''), ...(t.aliases || []).map(norm)].includes(qn)
        );

        const frag = document.createDocumentFragment();
        grouped.forEach((rows, cat) => {
            const head = document.createElement('div');
            head.className = 'px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-base-content/50';
            head.textContent = cat;
            frag.appendChild(head);
            rows.forEach((t) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.dataset.tsItem = '1';
                b.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-base-200';
                const local = displayLabel(t, locale);
                const en = t.labels?.en && norm(t.labels.en) !== norm(local) ? ` / ${t.labels.en}` : '';
                b.textContent = `${local}${en}`;
                b.addEventListener('click', () => {
                    addWithAttached(t.id);
                    input.value = '';
                    closeResults();
                });
                frag.appendChild(b);
            });
        });

        if (!exact) {
            const make = document.createElement('button');
            make.type = 'button';
            make.dataset.tsItem = '1';
            make.className = 'block w-full border-t border-base-300 px-3 py-2 text-left text-sm font-medium text-primary hover:bg-base-200';
            make.textContent = `+ ${cfg.strings?.createTag || 'Create tag'}: "${q.trim()}"`;
            make.addEventListener('click', () => {
                addNewTagLabel(q.trim());
                input.value = '';
                closeResults();
            });
            frag.appendChild(make);
        }

        if (!found.length && exact) {
            closeResults();
            return;
        }

        results.appendChild(frag);
        results.classList.remove('hidden');
    }

    input.addEventListener('input', () => buildResults(input.value));
    input.addEventListener('focus', () => {
        if (input.value.trim().length) buildResults(input.value);
    });
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
        if (e.key === 'Enter') {
            e.preventDefault();
            const q = input.value.trim();
            if (!q) {
                closeResults();
                return;
            }
            if (dropdownOpen && activeIndex >= 0) {
                items[activeIndex]?.click();
                return;
            }
            const exact = exactTagForQuery(q);
            if (exact) {
                addWithAttached(exact.id);
                input.value = '';
                closeResults();
                return;
            }
            addNewTagLabel(q);
            input.value = '';
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

