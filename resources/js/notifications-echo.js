let subscribedToUserNotifications = false;

function subscribeToUserNotifications() {
    if (subscribedToUserNotifications || typeof window.Livewire === 'undefined' || !window.Echo) {
        return;
    }

    const userId = document.body?.dataset?.userId;
    if (!userId) {
        return;
    }

    subscribedToUserNotifications = true;

    window.Echo.private(`App.Models.User.${userId}`).notification(() => {
        window.Livewire.dispatch('database-notifications-updated');
    });
}

// Livewire’s inline script can run before deferred Vite bundles, so `livewire:init` may
// fire before this module executes (same pattern as resources/js/maps-init.js).
document.addEventListener('livewire:init', subscribeToUserNotifications);
document.addEventListener('livewire:initialized', subscribeToUserNotifications);
document.addEventListener('DOMContentLoaded', subscribeToUserNotifications);
