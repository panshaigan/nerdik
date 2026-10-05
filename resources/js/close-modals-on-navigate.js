function closeMaryModalsInDocument(root = document) {
    root.querySelectorAll('dialog.modal').forEach((dialog) => {
        if (dialog.id === 'ui-session-expired-modal') {
            return;
        }

        const data = dialog.__x?.$data;
        if (data) {
            if ('isOpen' in data) {
                data.isOpen = false;
            } else if ('open' in data) {
                data.open = false;
            }
        }

        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
        }

        dialog.classList.remove('modal-open', '!animate-none');
        dialog.removeAttribute('open');
    });
}

function closeLivewireModalsBeforeNavigate() {
    if (typeof window.Livewire?.all !== 'function') {
        return;
    }

    const modalProperties = [
        'eventPreviewModalOpen',
        'activityPreviewModalOpen',
        'confirmModalOpen',
        'editSeriesModalOpen',
    ];

    window.Livewire.all().forEach((component) => {
        const wire = component?.$wire;

        if (!wire || typeof wire.get !== 'function' || typeof wire.set !== 'function') {
            return;
        }

        modalProperties.forEach((property) => {
            if (wire.get(property) === true) {
                wire.set(property, false);
            }
        });
    });
}

function markNavigating() {
    document.documentElement.classList.add('ui-navigating');
}

function unmarkNavigating() {
    document.documentElement.classList.remove('ui-navigating');
}

function prepareForNavigate() {
    markNavigating();
    closeLivewireModalsBeforeNavigate();
    closeMaryModalsInDocument();
}

document.addEventListener('livewire:navigate', prepareForNavigate, { capture: true });

document.addEventListener('livewire:navigating', (event) => {
    prepareForNavigate();

    if (typeof event.detail?.onSwap === 'function') {
        event.detail.onSwap(() => {
            closeMaryModalsInDocument();
        });
    }
});

document.addEventListener('livewire:navigated', () => {
    closeMaryModalsInDocument();
    unmarkNavigating();
});
