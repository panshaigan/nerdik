let subscribedToUserNotifications = false;

const proposalSubmittedBroadcastType =
    'App\\Notifications\\ProposalSubmittedNotification';

const livewireProposalRefreshKey = 'proposal_submitted_for_event';

function parseEchoNotificationPayload(notification) {
    let payload = notification;

    if (typeof payload === 'string') {
        try {
            payload = JSON.parse(payload);
        } catch {
            return null;
        }
    }

    if (payload && typeof payload === 'object') {
        if (typeof payload.data === 'string') {
            try {
                payload = JSON.parse(payload.data);
            } catch {
                return payload;
            }
        } else if (payload.data && typeof payload.data === 'object') {
            payload = payload.data;
        }
    }

    return payload && typeof payload === 'object' ? payload : null;
}

function subscribeToUserNotifications() {
    if (subscribedToUserNotifications || typeof window.Livewire === 'undefined' || !window.Echo) {
        return;
    }

    const userId = document.body?.dataset?.userId;
    if (!userId) {
        return;
    }

    subscribedToUserNotifications = true;

    window.Echo.private(`App.Models.User.${userId}`).notification((notification) => {
        window.Livewire.dispatch('database-notifications-updated');

        const payload = parseEchoNotificationPayload(notification);
        if (typeof window.toast === 'function') {
            const fallbackTitleByType = {
                proposal_submitted: 'Proposal submitted',
                proposal_accepted: 'Proposal accepted',
                proposal_rejected: 'Proposal rejected',
                waitlist_promoted: 'You got a place!',
                activity_cancelled: 'Activity cancelled',
                event_cancelled: 'Event cancelled',
            };

            const toastTitle = payload?.toast_title
                || (typeof payload?.type === 'string' ? fallbackTitleByType[payload.type] : null)
                || 'New notification';
            const toastDescription = payload?.toast_description
                || payload?.activity_name
                || payload?.event_name
                || '';

            window.toast({
                toast: {
                    type: 'info',
                    title: toastTitle,
                    description: toastDescription,
                    icon: '',
                    css: 'alert-info',
                    timeout: 4000,
                    noProgress: false,
                },
            });
        }

        const eventRaw = payload?.event_id;
        if (eventRaw === undefined || eventRaw === null || Number.isNaN(Number(eventRaw))) {
            return;
        }

        const shouldRefreshProposalUi =
            payload.lw_event_refresh === livewireProposalRefreshKey
            || payload.type === proposalSubmittedBroadcastType;

        if (!shouldRefreshProposalUi) {
            return;
        }

        window.Livewire.dispatch('event-proposal-submitted-broadcast', {
            eventId: Number(eventRaw),
        });
    });
}

// Livewire’s inline script can run before deferred Vite bundles, so `livewire:init` may
// fire before this module executes (same pattern as resources/js/maps-init.js).
document.addEventListener('livewire:init', subscribeToUserNotifications);
document.addEventListener('livewire:initialized', subscribeToUserNotifications);
document.addEventListener('DOMContentLoaded', subscribeToUserNotifications);
