let subscribedToSessionInvalidated = false;

function subscribeToSessionInvalidated() {
    if (
        subscribedToSessionInvalidated
        || typeof window.Echo === 'undefined'
        || !window.Echo
        || typeof window.Echo.private !== 'function'
    ) {
        return;
    }

    const userId = document.body?.dataset?.userId;
    if (!userId) {
        return;
    }

    subscribedToSessionInvalidated = true;

    window.Echo.private(`App.Models.User.${userId}`).listen(
        '.SessionInvalidated',
        () => {
            window.__nerdikSessionExpiredHandled = true;
            window.dispatchEvent(new CustomEvent('session-expired'));
        },
    );
}

document.addEventListener('livewire:init', subscribeToSessionInvalidated);
document.addEventListener('livewire:initialized', subscribeToSessionInvalidated);
document.addEventListener('DOMContentLoaded', subscribeToSessionInvalidated);

