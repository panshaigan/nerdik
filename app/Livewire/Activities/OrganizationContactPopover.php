<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class OrganizationContactPopover extends Component
{
    public int $targetOrganizationId;

    /**
     * @return Builder<Activity>
     */
    private function organizationActivitiesQuery(int $organizationId): Builder
    {
        return Activity::query()
            ->whereNull('activities.cancelled_at')
            ->whereNull('activities.deleted_at')
            ->whereHas('slot', fn (Builder $query) => $query
                ->whereNull('slots.deleted_at')
                ->whereHas('event', fn (Builder $eventQuery) => $eventQuery
                    ->where('organization_id', $organizationId)
                    ->whereNull('events.deleted_at')
                )
            );
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function scheduledStatsByType(int $organizationId): array
    {
        return $this->organizationActivitiesQuery($organizationId)
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
    private function participantStatsByType(int $organizationId): array
    {
        return ActivityUser::query()
            ->selectRaw('activity_types.slug as type_slug, count(*) as total')
            ->join('activities', 'activities.id', '=', 'activity_user.activity_id')
            ->join('slots', 'slots.activity_id', '=', 'activities.id')
            ->join('events', 'events.id', '=', 'slots.event_id')
            ->leftJoin('activity_types', 'activity_types.id', '=', 'activities.activity_type_id')
            ->where('events.organization_id', $organizationId)
            ->whereNull('activity_user.deleted_at')
            ->whereNull('activities.deleted_at')
            ->whereNull('activities.cancelled_at')
            ->whereNull('slots.deleted_at')
            ->whereNull('events.deleted_at')
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
     * @return array{
     *     targetOrganization: ?Organization,
     *     scheduledStatsByType: array<int, array{label: string, count: int}>,
     *     participantStatsByType: array<int, array{label: string, count: int}>,
     *     members: Collection<int, User>,
     * }
     */
    private function resolveViewData(): array
    {
        if (! auth()->check()) {
            return $this->emptyState();
        }

        $targetOrganization = Organization::query()->whereKey($this->targetOrganizationId)->first();

        if (! $targetOrganization instanceof Organization) {
            return $this->emptyState();
        }

        return [
            'targetOrganization' => $targetOrganization,
            'scheduledStatsByType' => $this->scheduledStatsByType($targetOrganization->id),
            'participantStatsByType' => $this->participantStatsByType($targetOrganization->id),
            'members' => $this->organizationMembers($targetOrganization->id),
        ];
    }

    /**
     * @return array{
     *     targetOrganization: ?Organization,
     *     scheduledStatsByType: array<int, array{label: string, count: int}>,
     *     participantStatsByType: array<int, array{label: string, count: int}>,
     *     members: Collection<int, User>,
     * }
     */
    private function emptyState(): array
    {
        return [
            'targetOrganization' => null,
            'scheduledStatsByType' => [],
            'participantStatsByType' => [],
            'members' => collect(),
        ];
    }

    public function render()
    {
        return view('livewire.activities.organization-contact-popover', $this->resolveViewData());
    }
}
