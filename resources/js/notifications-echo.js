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

document.addEventListener('livewire:init', subscribeToUserNotifications);
