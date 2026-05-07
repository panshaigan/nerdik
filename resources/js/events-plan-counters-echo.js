let subscribedEventId = null;
let subscribedActivityIds = new Set();

function teardownEventPlanCounterChannels() {
    if (!window.Echo || typeof window.Echo.leave !== 'function') {
        subscribedEventId = null;
        subscribedActivityIds = new Set();
        return;
    }

    for (const activityId of subscribedActivityIds) {
        window.Echo.leave(`activity.${activityId}`);
    }

    subscribedEventId = null;
    subscribedActivityIds = new Set();
}

function parseActivityIds(rawIds) {
    if (!rawIds) {
        return [];
    }

    try {
        const parsed = JSON.parse(rawIds);
        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed
            .map((id) => Number(id))
            .filter((id) => Number.isInteger(id) && id > 0);
    } catch {
        return [];
    }
}

function subscribeEventPlanCounterChannels() {
    if (typeof window.Livewire === 'undefined' || !window.Echo) {
        return;
    }

    const eventRoot = document.querySelector('[data-show-event-id]');
    if (!eventRoot?.dataset?.showEventId) {
        teardownEventPlanCounterChannels();
        return;
    }

    const eventId = String(eventRoot.dataset.showEventId);
    const activityIds = parseActivityIds(eventRoot.dataset.showEventActivityIds);
    const nextActivityIds = new Set(activityIds.map((id) => String(id)));

    if (subscribedEventId !== eventId) {
        teardownEventPlanCounterChannels();
    } else {
        for (const activityId of subscribedActivityIds) {
            if (!nextActivityIds.has(activityId)) {
                window.Echo.leave(`activity.${activityId}`);
                subscribedActivityIds.delete(activityId);
            }
        }
    }

    subscribedEventId = eventId;

    for (const activityId of nextActivityIds) {
        if (subscribedActivityIds.has(activityId)) {
            continue;
        }

        subscribedActivityIds.add(activityId);

        window.Echo.private(`activity.${activityId}`).listen('.activity.participation.updated', (payload) => {
            const raw = payload?.activityId;
            if (raw === undefined || raw === null || Number.isNaN(Number(raw))) {
                return;
            }

            window.Livewire.dispatch('event-plan-activity-participation-updated', {
                activityId: Number(raw),
            });
        });
    }
}

document.addEventListener('livewire:init', subscribeEventPlanCounterChannels);
document.addEventListener('livewire:initialized', subscribeEventPlanCounterChannels);
document.addEventListener('DOMContentLoaded', subscribeEventPlanCounterChannels);
document.addEventListener('livewire:navigated', () => {
    teardownEventPlanCounterChannels();
    subscribeEventPlanCounterChannels();
});
