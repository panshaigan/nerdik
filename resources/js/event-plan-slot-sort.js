/**
 * Swap-preview while dragging activities between plan slots (pairs with Livewire wire:sort).
 */
const planSortState = {
    sourceSlot: null,
    displacedNode: null,
    displacedFrom: null,
    rafId: null,
    dropCommitted: false,
    sortingWatchdogId: null,
    hooksRegistered: false,
};

function isSortItem(el) {
    if (!(el instanceof HTMLElement)) {
        return false;
    }

    return el.hasAttribute('data-event-plan-sort-item') || el.hasAttribute('wire:sort:item');
}

function slotSortItems(slotEl) {
    return [...slotEl.children].filter((child) => isSortItem(child));
}

function isDragArtifact(el) {
    if (!(el instanceof HTMLElement)) {
        return false;
    }

    return el.classList.contains('sortable-chosen')
        || el.classList.contains('sortable-ghost')
        || el.classList.contains('sortable-drag');
}

function sortEnabledRoot() {
    return document.querySelector('[data-ui="event-show-slots"][data-plan-sort-enabled="1"]');
}

function captureSlotHeights(root) {
    root.querySelectorAll('[data-event-plan-sort-slot]').forEach((slotEl) => {
        slotEl.style.setProperty('--plan-sort-slot-min-height', `${slotEl.offsetHeight}px`);
    });
}

function clearSlotHeights(root) {
    root.querySelectorAll('[data-event-plan-sort-slot]').forEach((slotEl) => {
        slotEl.style.removeProperty('--plan-sort-slot-min-height');
        delete slotEl.dataset.planSortSource;
        delete slotEl.dataset.planSortAwaiting;
    });
}

function resolveSourceSlot(root) {
    const chosen = root.querySelector('[data-event-plan-sort-item].sortable-chosen, [wire\\:sort\\:item].sortable-chosen');
    if (!chosen) {
        return planSortState.sourceSlot;
    }

    const slot = chosen.closest('[data-event-plan-sort-slot]');
    if (slot) {
        slot.dataset.planSortSource = '1';
    }

    return slot;
}

function revertSwapPreview() {
    if (!planSortState.displacedNode || !planSortState.displacedFrom) {
        return;
    }

    if (!document.body.contains(planSortState.displacedNode)) {
        planSortState.displacedNode = null;
        planSortState.displacedFrom = null;

        return;
    }

    if (planSortState.displacedNode.parentElement !== planSortState.displacedFrom) {
        planSortState.displacedFrom.appendChild(planSortState.displacedNode);
    }

    delete planSortState.displacedFrom.dataset.planSortAwaiting;
    planSortState.displacedNode = null;
    planSortState.displacedFrom = null;
}

function tickSwapPreview(root) {
    if (!document.body.classList.contains('sorting')) {
        return;
    }

    planSortState.sourceSlot = resolveSourceSlot(root) ?? planSortState.sourceSlot;
    const sourceSlot = planSortState.sourceSlot;

    if (!sourceSlot) {
        return;
    }

    const slots = [...root.querySelectorAll('[data-event-plan-sort-slot]')];
    let hoveringSwapTarget = false;

    for (const slot of slots) {
        if (slot === sourceSlot) {
            continue;
        }

        const items = slotSortItems(slot);
        if (items.length === 0) {
            continue;
        }

        const resident = items.find((el) => !isDragArtifact(el));
        const incomingCount = items.filter((el) => isDragArtifact(el)).length;
        const hasIncoming = incomingCount > 0 || items.length > 1;

        if (!resident || !hasIncoming) {
            continue;
        }

        const sourceItems = slotSortItems(sourceSlot);
        const sourceIsEmpty = sourceItems.length === 0
            || sourceItems.every((el) => isDragArtifact(el));

        if (!sourceIsEmpty) {
            continue;
        }

        hoveringSwapTarget = true;

        if (planSortState.displacedNode !== resident) {
            revertSwapPreview();
            planSortState.displacedNode = resident;
            planSortState.displacedFrom = slot;
            sourceSlot.appendChild(resident);
            slot.dataset.planSortAwaiting = '1';
        }

        break;
    }

    if (!hoveringSwapTarget) {
        revertSwapPreview();
    }
}

function runPreviewLoop() {
    const root = sortEnabledRoot();
    if (!root || !document.body.classList.contains('sorting')) {
        stopPreviewLoop();
        return;
    }

    tickSwapPreview(root);
    planSortState.rafId = window.requestAnimationFrame(runPreviewLoop);
}

function clearSortingWatchdog() {
    if (planSortState.sortingWatchdogId !== null) {
        window.clearTimeout(planSortState.sortingWatchdogId);
        planSortState.sortingWatchdogId = null;
    }
}

function armSortingWatchdog() {
    clearSortingWatchdog();
    planSortState.sortingWatchdogId = window.setTimeout(() => {
        if (!document.body.classList.contains('sorting')) {
            return;
        }

        planSortState.dropCommitted = false;
        document.body.classList.remove('sorting');
        stopPreviewLoop();
    }, 12000);
}

function stopPreviewLoop() {
    if (planSortState.rafId !== null) {
        window.cancelAnimationFrame(planSortState.rafId);
        planSortState.rafId = null;
    }

    clearSortingWatchdog();

    const shouldRevertPreview = !planSortState.dropCommitted;
    planSortState.dropCommitted = false;

    const root = sortEnabledRoot();

    window.requestAnimationFrame(() => {
        if (shouldRevertPreview) {
            revertSwapPreview();
        } else {
            planSortState.displacedNode = null;
            planSortState.displacedFrom = null;
        }

        planSortState.sourceSlot = null;

        if (root) {
            clearSlotHeights(root);
        }
    });
}

function startPreviewLoop() {
    const root = sortEnabledRoot();
    if (!root) {
        return;
    }

    planSortState.dropCommitted = false;
    captureSlotHeights(root);
    planSortState.sourceSlot = null;

    if (planSortState.rafId === null) {
        planSortState.rafId = window.requestAnimationFrame(runPreviewLoop);
    }

    armSortingWatchdog();
}

function bindSortDropMarkers(root) {
    root.querySelectorAll('[data-event-plan-sort-slot]').forEach((slot) => {
        if (slot.dataset.planSortBound === '1') {
            return;
        }

        slot.dataset.planSortBound = '1';
        slot.addEventListener('sort', () => {
            planSortState.dropCommitted = true;
        });
    });
}

function resetAfterMorph() {
    if (planSortState.rafId !== null) {
        window.cancelAnimationFrame(planSortState.rafId);
        planSortState.rafId = null;
    }

    clearSortingWatchdog();
    planSortState.dropCommitted = false;
    planSortState.displacedNode = null;
    planSortState.displacedFrom = null;
    planSortState.sourceSlot = null;

    if (document.body.classList.contains('sorting')) {
        document.body.classList.remove('sorting');
    }

    const root = sortEnabledRoot();
    if (root) {
        clearSlotHeights(root);
        bindSortDropMarkers(root);
    }
}

function watchSortingState() {
    if (window.__nerdikPlanSortBodyObserver) {
        return;
    }

    const observer = new MutationObserver(() => {
        if (document.body.classList.contains('sorting')) {
            startPreviewLoop();
        } else {
            stopPreviewLoop();
        }
    });

    observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    window.__nerdikPlanSortBodyObserver = observer;
}

function registerLivewirePlanSortHooks() {
    if (planSortState.hooksRegistered || !window.Livewire?.hook) {
        return;
    }

    window.Livewire.hook('morphed', () => {
        if (!sortEnabledRoot()) {
            return;
        }

        resetAfterMorph();
    });

    planSortState.hooksRegistered = true;
}

export function initEventPlanSlotSort() {
    const root = sortEnabledRoot();
    if (!root) {
        return;
    }

    registerLivewirePlanSortHooks();
    watchSortingState();
    bindSortDropMarkers(root);

    if (document.body.classList.contains('sorting')) {
        startPreviewLoop();
    } else {
        resetAfterMorph();
    }
}

document.addEventListener('livewire:init', registerLivewirePlanSortHooks);
document.addEventListener('livewire:initialized', registerLivewirePlanSortHooks);