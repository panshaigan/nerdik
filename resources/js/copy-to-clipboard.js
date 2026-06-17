function toastCopyResult({ type, title }) {
    if (typeof window.toast !== 'function') {
        return;
    }

    window.toast({
        toast: {
            type,
            title,
            css: type === 'success' ? 'alert-success' : 'alert-error',
            timeout: 2500,
            icon: '',
        },
    });
}

export async function copyToClipboard(text, { message } = {}) {
    const copiedMessage = message ?? window.__ui?.copied ?? 'Copied!';
    const failedMessage = window.__ui?.copy_failed ?? 'Could not copy.';

    try {
        if (!navigator.clipboard?.writeText) {
            throw new Error('Clipboard API unavailable');
        }

        await navigator.clipboard.writeText(text);
        toastCopyResult({ type: 'success', title: copiedMessage });
    } catch {
        toastCopyResult({ type: 'error', title: failedMessage });
    }
}

window.copyToClipboard = copyToClipboard;
