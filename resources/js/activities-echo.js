let activityParticipationSubscribedId = null;

function teardownActivityParticipationEchoChannel() {
    if (activityParticipationSubscribedId !== null && window.Echo && typeof window.Echo.leave === 'function') {
        window.Echo.leave(`activity.${activityParticipationSubscribedId}`);
    }
    activityParticipationSubscribedId = null;
}

function subscribeActivityParticipationEchoChannel() {
    if (typeof window.Livewire === 'undefined' || !window.Echo) {
        return;
    }

    const el = document.querySelector('[data-show-activity-id]');
    if (!el?.dataset.showActivityId) {
        teardownActivityParticipationEchoChannel();
        return;
    }

    const id = el.dataset.showActivityId;

    if (activityParticipationSubscribedId === id) {
        return;
    }

    teardownActivityParticipationEchoChannel();

    activityParticipationSubscribedId = id;

    window.Echo.private(`activity.${id}`).listen('.activity.participation.updated', (payload) => {
        const raw = payload?.activityId;
        if (raw === undefined || raw === null || Number.isNaN(Number(raw))) {
            return;
        }

        window.Livewire.dispatch('activity-participation-updated', {
            activityId: Number(raw),
        });
    });
}

document.addEventListener('livewire:init', subscribeActivityParticipationEchoChannel);
document.addEventListener('livewire:initialized', subscribeActivityParticipationEchoChannel);
document.addEventListener('DOMContentLoaded', subscribeActivityParticipationEchoChannel);
document.addEventListener('livewire:navigated', () => {
    teardownActivityParticipationEchoChannel();
    subscribeActivityParticipationEchoChannel();
});
