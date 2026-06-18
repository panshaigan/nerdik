let subscribedToAvatarReady = false;

function subscribeToAvatarReady() {
    if (
        subscribedToAvatarReady
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

    subscribedToAvatarReady = true;

    window.Echo.private(`App.Models.User.${userId}`).listen(
        '.UserAvatarReady',
        () => {
            if (typeof window.Livewire !== 'undefined' && typeof window.Livewire.dispatch === 'function') {
                window.Livewire.dispatch('profile-avatar-updated');
            }

            window.dispatchEvent(new CustomEvent('profile-avatar-updated'));
        },
    );
}

document.addEventListener('livewire:init', subscribeToAvatarReady);
document.addEventListener('livewire:initialized', subscribeToAvatarReady);
document.addEventListener('DOMContentLoaded', subscribeToAvatarReady);
