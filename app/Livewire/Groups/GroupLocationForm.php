<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Services\GroupDiscoveryPreferencesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

class GroupLocationForm extends Component
{
    use Toast;

    #[Locked]
    public int $groupId;

    public ?float $playLatitude = null;

    public ?float $playLongitude = null;

    public int $playRadiusKm = GroupDiscoveryPreferencesService::DEFAULT_RADIUS_KM;

    public ?string $playLabel = null;

    public ?int $playCityId = null;

    public function mount(int $groupId): void
    {
        $this->groupId = $groupId;
        $this->hydrateFromGroup($this->resolveGroup());
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlayAreaMapConfigProperty(): array
    {
        return [
            'searchUrl' => route('geocode.search'),
            'reverseUrl' => route('geocode.reverse'),
            'latitude' => $this->playLatitude,
            'longitude' => $this->playLongitude,
            'radiusKm' => $this->playRadiusKm,
            'label' => $this->playLabel,
            'cityId' => $this->playCityId,
            'minRadiusKm' => GroupDiscoveryPreferencesService::MIN_RADIUS_KM,
            'maxRadiusKm' => GroupDiscoveryPreferencesService::MAX_RADIUS_KM,
            'defaultRadiusKm' => GroupDiscoveryPreferencesService::DEFAULT_RADIUS_KM,
        ];
    }

    public function setPlayAreaDraft(
        ?float $latitude,
        ?float $longitude,
        int $radiusKm,
        ?string $label,
        ?int $cityId = null,
    ): void {
        $this->playLatitude = $latitude;
        $this->playLongitude = $longitude;
        $this->playRadiusKm = max(
            GroupDiscoveryPreferencesService::MIN_RADIUS_KM,
            min(GroupDiscoveryPreferencesService::MAX_RADIUS_KM, $radiusKm),
        );
        $normalized = $label !== null ? trim($label) : null;
        $this->playLabel = $normalized === '' ? null : $normalized;
        $this->playCityId = $cityId;
        $this->skipRender();
    }

    public function save(GroupDiscoveryPreferencesService $preferences): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $group = $this->resolveGroup();

        $this->validate([
            'playLatitude' => ['nullable', 'numeric', 'between:-90,90'],
            'playLongitude' => ['nullable', 'numeric', 'between:-180,180'],
            'playRadiusKm' => [
                'integer',
                'min:'.GroupDiscoveryPreferencesService::MIN_RADIUS_KM,
                'max:'.GroupDiscoveryPreferencesService::MAX_RADIUS_KM,
            ],
            'playLabel' => ['nullable', 'string', 'max:255'],
            'playCityId' => ['nullable', 'integer', 'exists:cities,id'],
        ]);

        try {
            if ($this->playLatitude !== null && $this->playLongitude !== null) {
                $preferences->saveLocation(
                    $group,
                    $user,
                    (float) $this->playLatitude,
                    (float) $this->playLongitude,
                    (int) $this->playRadiusKm,
                    $this->playLabel,
                    $this->playCityId,
                );
            } else {
                $preferences->clearLocation($group, $user);
            }
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->hydrateFromGroup($group->refresh());
        $this->success(__('ui.groups.location_saved'));
        $this->dispatch('group-location-updated');
    }

    public function render()
    {
        $group = $this->resolveGroup();

        return view('livewire.groups.group-location-form', [
            'group' => $group,
            'canManage' => Auth::user()?->canModifyEntity($group) ?? false,
        ]);
    }

    private function hydrateFromGroup(Group $group): void
    {
        if ($group->hasLocation()) {
            $this->playLatitude = (float) $group->latitude;
            $this->playLongitude = (float) $group->longitude;
            $this->playRadiusKm = (int) $group->radius_km;
            $this->playLabel = $group->location_label;
            $this->playCityId = $group->city_id !== null ? (int) $group->city_id : null;
        } else {
            $this->playLatitude = null;
            $this->playLongitude = null;
            $this->playRadiusKm = GroupDiscoveryPreferencesService::DEFAULT_RADIUS_KM;
            $this->playLabel = null;
            $this->playCityId = null;
        }
    }

    private function resolveGroup(): Group
    {
        return Group::query()
            ->memberContentVisibleTo(Auth::user())
            ->whereKey($this->groupId)
            ->firstOrFail();
    }
}
