<?php

namespace App\Livewire\Events;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class ShowEventSeries extends Component
{
    public int $eventSeriesId;

    public string $tab = 'events';

    protected array $queryString = [
        'tab' => ['except' => 'events'],
    ];

    public function mount(EventSeries $eventSeries): void
    {
        abort_unless($eventSeries->isVisibleTo(auth()->user()), 404);

        $this->eventSeriesId = $eventSeries->id;
        $this->tab = $this->normalizeTab($this->tab);
    }

    public function updatedTab(string $value): void
    {
        $this->tab = $this->normalizeTab($value);
    }

    public function render(): View
    {
        $series = EventSeries::query()->whereKey($this->eventSeriesId)->firstOrFail();
        abort_unless($series->isVisibleTo(auth()->user()), 404);

        $viewer = auth()->user();
        $eventsQuery = $series->events()
            ->with(['creator', 'organization', 'places.city.translations'])
            ->orderBy('starts_at')
            ->orderBy('id');

        if ($viewer === null || (int) $viewer->id !== (int) $series->created_by) {
            $eventsQuery->where('is_public', true);
        }

        /** @var Collection<int, Event> $events */
        $events = $eventsQuery->get();

        $hosts = $this->uniqueHosts($events);
        $activities = $this->seriesActivities($events);
        $stats = $this->seriesStats($events, $activities);

        return view('livewire.events.show-event-series', [
            'series' => $series,
            'events' => $events,
            'hosts' => $hosts,
            'activities' => $activities,
            'stats' => $stats,
        ]);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return list<array{type: string, user: ?User, organization: ?Organization, label: string}>
     */
    private function uniqueHosts(Collection $events): array
    {
        $hosts = [];

        foreach ($events as $event) {
            if ($event->organization !== null) {
                $key = 'org:'.$event->organization->id;
                $hosts[$key] = [
                    'type' => 'organization',
                    'user' => null,
                    'organization' => $event->organization,
                    'label' => $event->organization->name,
                ];

                continue;
            }

            if ($event->creator !== null) {
                $key = 'user:'.$event->creator->id;
                $hosts[$key] = [
                    'type' => 'user',
                    'user' => $event->creator,
                    'organization' => null,
                    'label' => $event->creator->displayName(),
                ];
            }
        }

        return array_values($hosts);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return Collection<int, Activity>
     */
    private function seriesActivities(Collection $events): Collection
    {
        $eventIds = $events->pluck('id')->all();
        if ($eventIds === []) {
            return collect();
        }

        return Activity::query()
            ->whereHas('slot', fn ($q) => $q->whereIn('event_id', $eventIds))
            ->with(['creator', 'activityType', 'slot.event'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @param  Collection<int, Activity>  $activities
     * @return array{
     *   editions_count: int,
     *   upcoming_count: int,
     *   past_count: int,
     *   cancelled_count: int,
     *   activities_count: int,
     *   participants_total: int,
     *   participants_unique: int
     * }
     */
    private function seriesStats(Collection $events, Collection $activities): array
    {
        $now = now();
        $upcoming = $events->filter(fn (Event $e) => ! $e->isCancelled() && $e->starts_at !== null && $e->starts_at->gte($now))->count();
        $past = $events->filter(fn (Event $e) => ! $e->isCancelled() && $e->ends_at !== null && $e->ends_at->lt($now))->count();
        $cancelled = $events->filter(fn (Event $e) => $e->isCancelled())->count();

        $activityIds = $activities->pluck('id')->all();
        $participantsTotal = 0;
        $participantsUnique = 0;

        if ($activityIds !== []) {
            $participantsTotal = (int) ActivityUser::query()
                ->whereIn('activity_id', $activityIds)
                ->whereNull('deleted_at')
                ->where('is_absent', false)
                ->count();

            $participantsUnique = (int) ActivityUser::query()
                ->whereIn('activity_id', $activityIds)
                ->whereNull('deleted_at')
                ->where('is_absent', false)
                ->distinct('user_id')
                ->count('user_id');
        }

        return [
            'editions_count' => $events->count(),
            'upcoming_count' => $upcoming,
            'past_count' => $past,
            'cancelled_count' => $cancelled,
            'activities_count' => $activities->count(),
            'participants_total' => $participantsTotal,
            'participants_unique' => $participantsUnique,
        ];
    }

    private function normalizeTab(?string $value): string
    {
        return in_array($value, ['events', 'hosts', 'activities', 'stats'], true)
            ? $value
            : 'events';
    }
}
