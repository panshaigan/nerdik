<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use App\Models\EventSeries;
use App\Models\Organization;
use App\Models\Place;
use App\Models\User;
use App\Support\Browse\BrowseSearchUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class OrganizationContactPopover extends Component
{
    public int $targetOrganizationId;

    /**
     * @param  'upcoming'|'past'|null  $timeframe
     * @return Builder<Activity>
     */
    private function organizationActivitiesQuery(int $organizationId, ?string $timeframe = null): Builder
    {
        return Activity::query()
            ->whereNull('activities.cancelled_at')
            ->whereNull('activities.deleted_at')
            ->whereHas('slot', function (Builder $query) use ($organizationId, $timeframe): void {
                $query
                    ->whereNull('slots.deleted_at')
                    ->whereHas('event', fn (Builder $eventQuery) => $eventQuery
                        ->where('organization_id', $organizationId)
                        ->whereNull('events.deleted_at')
                    );

                if ($timeframe === 'upcoming') {
                    $query->whereRaw('COALESCE(slots.ends_at, slots.starts_at) >= ?', [now()]);
                } elseif ($timeframe === 'past') {
                    $query->whereRaw('COALESCE(slots.ends_at, slots.starts_at) < ?', [now()]);
                }
            });
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function activityStatsByType(Builder $query): array
    {
        return $query
            ->selectRaw('activity_types.slug as type_slug, count(*) as total')
            ->leftJoin('activity_types', 'activity_types.id', '=', 'activities.activity_type_id')
            ->groupBy('activity_types.slug')
            ->orderByRaw('count(*) desc')
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->type_slug ? __('ui.activities.types.'.$row->type_slug) : __('ui.common.none'),
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function scheduledStatsByType(int $organizationId): array
    {
        return $this->activityStatsByType(
            $this->organizationActivitiesQuery($organizationId, 'upcoming')
        );
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function pastStatsByType(int $organizationId): array
    {
        return $this->activityStatsByType(
            $this->organizationActivitiesQuery($organizationId, 'past')
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function organizationMembers(int $organizationId): Collection
    {
        return User::query()
            ->with('profile')
            ->where('organization_id', $organizationId)
            ->where('is_deleted', false)
            ->orderBy('nickname')
            ->get();
    }

    /**
     * @return Collection<int, EventSeries>
     */
    private function organizationEventSeries(int $organizationId): Collection
    {
        $viewer = auth()->user();

        return EventSeries::query()
            ->whereHas('events', fn (Builder $query) => $query
                ->where('organization_id', $organizationId)
                ->whereNull('deleted_at')
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->filter(fn (EventSeries $series): bool => $series->isVisibleTo($viewer))
            ->values();
    }

    /**
     * @return list<array{url: string, label: string}>
     */
    private function organizationPlaceLinks(int $organizationId): array
    {
        return Place::query()
            ->venues()
            ->with(['city.translations', 'country.translations'])
            ->whereHas('events', fn (Builder $query) => $query
                ->where('organization_id', $organizationId)
                ->whereNull('deleted_at')
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Place $place): array => [
                'url' => BrowseSearchUrl::forPlace($place),
                'label' => $place->compactVenueSummary(),
            ])
            ->filter(fn (array $link): bool => $link['label'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     targetOrganization: ?Organization,
     *     scheduledStatsByType: array<int, array{label: string, count: int}>,
     *     pastStatsByType: array<int, array{label: string, count: int}>,
     *     members: Collection<int, User>,
     *     eventSeries: Collection<int, EventSeries>,
     *     placeLinks: list<array{url: string, label: string}>,
     * }
     */
    private function resolveViewData(): array
    {
        $targetOrganization = Organization::query()
            ->whereKey($this->targetOrganizationId)
            ->with('links')
            ->first();

        if (! $targetOrganization instanceof Organization) {
            return $this->emptyState();
        }

        return [
            'targetOrganization' => $targetOrganization,
            'scheduledStatsByType' => $this->scheduledStatsByType($targetOrganization->id),
            'pastStatsByType' => $this->pastStatsByType($targetOrganization->id),
            'members' => $this->organizationMembers($targetOrganization->id),
            'eventSeries' => $this->organizationEventSeries($targetOrganization->id),
            'placeLinks' => $this->organizationPlaceLinks($targetOrganization->id),
        ];
    }

    /**
     * @return array{
     *     targetOrganization: ?Organization,
     *     scheduledStatsByType: array<int, array{label: string, count: int}>,
     *     pastStatsByType: array<int, array{label: string, count: int}>,
     *     members: Collection<int, User>,
     *     eventSeries: Collection<int, EventSeries>,
     *     placeLinks: list<array{url: string, label: string}>,
     * }
     */
    private function emptyState(): array
    {
        return [
            'targetOrganization' => null,
            'scheduledStatsByType' => [],
            'pastStatsByType' => [],
            'members' => collect(),
            'eventSeries' => collect(),
            'placeLinks' => [],
        ];
    }

    public function render()
    {
        return view('livewire.activities.organization-contact-popover', $this->resolveViewData());
    }
}
