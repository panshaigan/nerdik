function closeMaryModalsInDocument(root = document) {
    root.querySelectorAll('dialog.modal').forEach((dialog) => {
        if (dialog.id === 'ui-session-expired-modal') {
            return;
        }

        if (typeof dialog.__x !== 'undefined' && dialog.__x?.$data && 'open' in dialog.__x.$data) {
            dialog.__x.$data.open = false;
        }

        dialog.classList.remove('modal-open', '!animate-none');
        dialog.removeAttribute('open');
    });
}

function markNavigating() {
    document.documentElement.classList.add('ui-navigating');
}

function unmarkNavigating() {
    document.documentElement.classList.remove('ui-navigating');
}

document.addEventListener('livewire:navigate', markNavigating);

document.addEventListener('livewire:navigating', (event) => {
    markNavigating();

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
