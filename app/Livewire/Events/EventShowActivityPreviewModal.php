<?php

namespace App\Livewire\Events;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Enums\ActivityProposalStatus;
use App\Livewire\Concerns\WithActivityPreviewModal;
use App\Models\Activity;
use App\Models\Event;
use App\Services\ActivityParticipationViewService;
use App\Services\EventActivitySignupService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * Activity preview sheet for {@see ShowEvent}. Isolated so opening or switching previews
 * does not re-render the event shell or nested plan/proposals tab panels.
 */
class EventShowActivityPreviewModal extends Component
{
    use Toast;
    use WithActivityPreviewModal;

    #[Locked]
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function hydrate(): void
    {
        Event::query()->visibleTo(auth()->user())->whereKey($this->eventId)->firstOrFail();
    }

    #[On('open-event-activity-preview')]
    public function handleOpenEventActivityPreview(int $activityId): void
    {
        $this->openActivityPreview($activityId);
    }

    public function render(
        ActivityParticipationViewService $participationView,
        ActivityBadgeGroupBuilder $badgeGroupBuilder,
        EventActivitySignupService $signupService,
    ): View {
        return view('livewire.events.event-show-activity-preview-modal', $this->resolveActivityPreviewViewData(
            $participationView,
            $badgeGroupBuilder,
            $signupService,
        ));
    }

    protected function previewActivityQuery(int $activityId): Builder
    {
        return Activity::query()
            ->whereKey($activityId)
            ->where(function (Builder $query) {
                $query->whereHas('slot', fn ($q) => $q->where('event_id', $this->eventId))
                    ->orWhereHas(
                        'proposals',
                        fn ($q) => $q->where('event_id', $this->eventId)
                            ->where('status', ActivityProposalStatus::Pending),
                    );
            });
    }

    protected function showPreviewParticipationActions(?Activity $activity): bool
    {
        if ($activity === null) {
            return false;
        }

        return (int) ($activity->slot?->event_id) === (int) $this->eventId;
    }

    protected function previewActivityBelongsToParticipationBroadcast(int $activityId): bool
    {
        return Activity::query()
            ->whereKey($activityId)
            ->whereHas('slot', fn ($query) => $query->where('event_id', $this->eventId))
            ->exists();
    }

    protected function afterPreviewParticipationChanged(): void
    {
        $this->dispatch('event-show-plan-counter-bump');
    }
}
