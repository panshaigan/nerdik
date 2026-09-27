<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Models\Activity;
use App\Models\Place;
use App\Models\Slot;
use App\Support\Ui\ManageFormBackUrl;
use App\Traits\AuthorizesOwnership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ManagePlaceForm extends Component
{
    use AuthorizesOwnership;

    public int $editingPlaceId;

    public string $name = '';

    public ?string $address = null;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?int $city_id = null;

    public ?int $country_id = null;

    public string $type = Place::TYPE_VENUE;

    /**
     * @var list<array{key: string, id: int|null, name: string}>
     */
    public array $rooms = [];

    /**
     * Soft-delete these room ids on save (after in-use checks).
     *
     * @var list<int>
     */
    public array $removedRoomIds = [];

    public function mount(Place $place): void
    {
        $this->authorizeCreatedBy($place);
        $this->hydrateFormFromPlace($place);
        ManageFormBackUrl::captureFromRequest();
    }

    public function addRoom(): void
    {
        $this->rooms[] = [
            'key' => (string) Str::uuid(),
            'id' => null,
            'name' => '',
        ];
    }

    public function removeRoom(string $key): void
    {
        foreach ($this->rooms as $index => $room) {
            if (($room['key'] ?? '') !== $key) {
                continue;
            }

            $id = $room['id'] ?? null;
            if (is_int($id) || (is_numeric($id) && (int) $id > 0)) {
                $this->removedRoomIds[] = (int) $id;
                $this->removedRoomIds = array_values(array_unique($this->removedRoomIds));
            }

            unset($this->rooms[$index]);
            $this->rooms = array_values($this->rooms);

            return;
        }
    }

    public function save()
    {
        $place = $this->editingPlace();
        $this->authorizeCreatedBy($place);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'rooms' => ['array'],
            'rooms.*.name' => ['nullable', 'string', 'max:255'],
            'rooms.*.id' => ['nullable', 'integer'],
            'removedRoomIds' => ['array'],
            'removedRoomIds.*' => ['integer'],
        ]);

        $roomRows = collect($validated['rooms'] ?? [])
            ->map(function (array $row): array {
                return [
                    'id' => isset($row['id']) && $row['id'] !== null && $row['id'] !== ''
                        ? (int) $row['id']
                        : null,
                    'name' => trim((string) ($row['name'] ?? '')),
                ];
            })
            ->filter(fn (array $row): bool => $row['name'] !== '')
            ->values()
            ->all();

        $removedIds = array_values(array_unique(array_map('intval', $validated['removedRoomIds'] ?? [])));

        try {
            DB::transaction(function () use ($place, $validated, $roomRows, $removedIds): void {
                $place->update([
                    'name' => $validated['name'],
                    'address' => filled($validated['address'] ?? null) ? trim((string) $validated['address']) : null,
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'city_id' => $validated['city_id'] ?? null,
                    'country_id' => $validated['country_id'] ?? null,
                ]);

                if ($place->type !== Place::TYPE_VENUE) {
                    return;
                }

                $this->assertRemovedRoomsAreDeletable($place, $removedIds);

                foreach ($removedIds as $roomId) {
                    $room = Place::query()
                        ->whereKey($roomId)
                        ->where('parent_id', $place->id)
                        ->where('type', Place::TYPE_ROOM)
                        ->first();

                    $room?->delete();
                }

                foreach ($roomRows as $row) {
                    if ($row['id'] !== null) {
                        $room = Place::query()
                            ->whereKey($row['id'])
                            ->where('parent_id', $place->id)
                            ->where('type', Place::TYPE_ROOM)
                            ->first();

                        if ($room === null) {
                            continue;
                        }

                        $room->update(['name' => $row['name']]);

                        continue;
                    }

                    Place::create([
                        'name' => $row['name'],
                        'type' => Place::TYPE_ROOM,
                        'parent_id' => $place->id,
                        'address' => $place->address,
                        'city_id' => $place->city_id,
                        'country_id' => $place->country_id,
                        'latitude' => $place->latitude,
                        'longitude' => $place->longitude,
                        'is_online' => false,
                    ]);
                }
            });
        } catch (ValidationException $e) {
            throw $e;
        }

        session()->flash('status', __('ui.places.updated'));

        return redirect()->to($this->cancelUrl());
    }

    public function editingPlace(): Place
    {
        return Place::query()->whereKey($this->editingPlaceId)->firstOrFail();
    }

    /**
     * @return array{searchUrl: string, reverseUrl: string, latitude: float|null, longitude: float|null, address: string|null}
     */
    public function getMapConfigProperty(): array
    {
        return [
            'searchUrl' => route('geocode.search'),
            'reverseUrl' => route('geocode.reverse'),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
        ];
    }

    public function render()
    {
        $place = $this->editingPlace();

        return view('livewire.places.manage-place-form', [
            'place' => $place,
            'backUrl' => ManageFormBackUrl::resolve(route('catalog.places')),
            'cancelUrl' => $this->cancelUrl(),
            'submitLabel' => __('ui.common.update'),
            'creator' => $place->creator,
            'isVenue' => $place->type === Place::TYPE_VENUE,
        ]);
    }

    protected function hydrateFormFromPlace(Place $place): void
    {
        $this->editingPlaceId = $place->id;
        $this->name = (string) $place->name;
        $this->address = $place->address;
        $this->latitude = $place->latitude !== null ? (float) $place->latitude : null;
        $this->longitude = $place->longitude !== null ? (float) $place->longitude : null;
        $this->city_id = $place->city_id !== null ? (int) $place->city_id : null;
        $this->country_id = $place->country_id !== null ? (int) $place->country_id : null;
        $this->type = (string) $place->type;
        $this->removedRoomIds = [];

        $this->rooms = $place->type === Place::TYPE_VENUE
            ? Place::query()
                ->where('parent_id', $place->id)
                ->where('type', Place::TYPE_ROOM)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Place $room): array => [
                    'key' => 'room-'.$room->id,
                    'id' => (int) $room->id,
                    'name' => (string) $room->name,
                ])
                ->values()
                ->all()
            : [];

        $this->resetErrorBag();
    }

    protected function cancelUrl(): string
    {
        return ManageFormBackUrl::resolve(route('catalog.places'));
    }

    /**
     * @param  list<int>  $removedIds
     */
    protected function assertRemovedRoomsAreDeletable(Place $venue, array $removedIds): void
    {
        if ($removedIds === []) {
            return;
        }

        $rooms = Place::query()
            ->whereIn('id', $removedIds)
            ->where('parent_id', $venue->id)
            ->where('type', Place::TYPE_ROOM)
            ->get(['id', 'name']);

        foreach ($rooms as $room) {
            $inUseBySlot = Slot::query()->where('place_id', $room->id)->exists();
            $inUseByActivity = Activity::query()->where('place_id', $room->id)->exists();

            if ($inUseBySlot || $inUseByActivity) {
                throw ValidationException::withMessages([
                    'rooms' => __('ui.places.room_in_use', ['name' => $room->name]),
                ]);
            }
        }
    }
}
