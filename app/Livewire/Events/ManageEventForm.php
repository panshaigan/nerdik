<?php

namespace App\Livewire\Events;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Tag;
use App\Services\LocationResolver;
use App\Services\TagSelectionService;
use App\Support\RichText;
use App\Traits\AuthorizesOwnership;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ManageEventForm extends Component
{
    use AuthorizesOwnership;

    public ?int $editingEventId = null;

    public string $name = '';

    public string $desc = '';

    public ?int $organization_id = null;

    public string $organization_name = '';

    public bool $is_public = true;

    public string $starts_at = '';

    public string $ends_at = '';

    /** @var list<int> */
    public array $tag_ids = [];

    /** @var list<array{label: string, category: string}> */
    public array $new_tags = [];

    /** @var list<int> */
    public array $place_ids = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $new_places = [];

    public function mount(?Event $event = null): void
    {
        if ($event?->exists) {
            $this->authorizeCreatedBy($event);
            $event->load(['tags', 'places', 'organization']);
            $this->editingEventId = $event->id;
            $this->name = (string) $event->name;
            $this->desc = (string) ($event->desc ?? '');
            $this->organization_id = $event->organization_id;
            $this->organization_name = (string) (optional($event->organization)->name ?? '');
            $this->is_public = (bool) $event->is_public;
            $this->starts_at = $event->starts_at ? format_in_user_tz($event->starts_at, 'Y-m-d\TH:i') : '';
            $this->ends_at = $event->ends_at ? format_in_user_tz($event->ends_at, 'Y-m-d\TH:i') : '';
            $this->tag_ids = $event->tags->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $this->new_tags = [];
            $this->place_ids = $event->places
                ->filter(fn (Place $p) => $p->type === 'venue')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
            $this->new_places = [];
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function prepareForValidation($attributes)
    {
        if ($this->organization_id === '' || $this->organization_id === 0) {
            $this->organization_id = null;
        } elseif ($this->organization_id !== null) {
            $this->organization_id = (int) $this->organization_id;
        }

        foreach (array_keys($attributes) as $key) {
            if (property_exists($this, $key)) {
                $attributes[$key] = $this->{$key};
            }
        }

        return $attributes;
    }

    public function save(TagSelectionService $tagSelectionService, LocationResolver $locationResolver)
    {
        $validated = $this->validate($this->rules());

        $validated['desc'] = $this->normalizeDesc($validated['desc'] ?? null);
        $validated['is_public'] = (bool) ($validated['is_public'] ?? true);

        $validated['starts_at'] = parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString();
        $validated['ends_at'] = parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString();

        $orgName = isset($validated['organization_name']) ? trim((string) $validated['organization_name']) : '';
        $validated['organization_id'] = $this->resolveOrganizationIdFromRequest(
            $validated['organization_id'] ?? null,
            $orgName !== '' ? $orgName : null
        );
        unset($validated['organization_name']);

        $tagIds = $tagSelectionService->resolveFinalTagIds(
            $this->tag_ids,
            $this->new_tags
        );
        $placeIds = $this->place_ids;
        unset($validated['tag_ids'], $validated['place_ids'], $validated['new_places'], $validated['new_tags']);

        unset($validated['slug']);

        if ($this->editingEventId !== null) {
            $event = Event::query()->findOrFail($this->editingEventId);
            $this->authorizeCreatedBy($event);
            $event->update($validated);
            $event->tags()->sync($tagIds);
            $this->syncEventPlaces($event, $placeIds, $this->new_places, $locationResolver);
            session()->flash('status', __('Event updated.'));

            return redirect()->route('events.show', $event);
        }

        $validated['created_by'] = Auth::id();
        $event = Event::create($validated);
        $event->tags()->sync($tagIds);
        $this->syncEventPlaces($event, $placeIds, $this->new_places, $locationResolver);
        session()->flash('status', __('Event created.'));

        return redirect()->route('events.index');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'organization_id' => ['nullable', Rule::exists('organizations', 'id')->where(fn ($q) => $q->where('created_by', Auth::id()))],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'place_ids' => ['nullable', 'array'],
            'place_ids.*' => ['integer', Rule::exists('places', 'id')->where(fn ($q) => $q->where('type', 'venue'))],
            'new_places' => ['nullable', 'array'],
            'new_places.*.name' => ['nullable', 'string', 'max:255'],
            'new_places.*.address' => ['nullable', 'string', 'max:500'],
            'new_places.*.city' => ['nullable', 'string', 'max:255'],
            'new_places.*.country' => ['nullable', 'string', 'max:255'],
            'new_places.*.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'new_places.*.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'new_places.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'new_places.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'new_tags' => ['nullable', 'array'],
            'new_tags.*.label' => ['nullable', 'string', 'max:255'],
            'new_tags.*.category' => ['nullable', Rule::in(TagSelectionService::CATEGORY_OPTIONS)],
        ];
    }

    protected function normalizeDesc(?string $html): ?string
    {
        return RichText::sanitize($html);
    }

    /**
     * @param  list<int>  $placeIds
     * @param  list<array<string, mixed>>  $newPlacesInput
     */
    protected function syncEventPlaces(Event $event, array $placeIds, array $newPlacesInput, LocationResolver $locationResolver): void
    {
        $placeIds = array_values(array_unique(array_map('intval', $placeIds)));
        $placeIds = Place::query()
            ->whereIn('id', $placeIds)
            ->venues()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($newPlacesInput as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $lat = $row['latitude'] ?? null;
            $lng = $row['longitude'] ?? null;
            if ($lat === null || $lat === '' || $lng === null || $lng === '') {
                continue;
            }
            $resolved = $locationResolver->resolvePlaceRow($row);
            $address = trim((string) ($row['address'] ?? ''));

            $newPlace = Place::create([
                'name' => $name,
                'address' => $address !== '' ? $address : null,
                'type' => 'venue',
                'city_id' => $resolved['city_id'],
                'country_id' => $resolved['country_id'],
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
                'is_online' => false,
            ]);
            $placeIds[] = $newPlace->id;
        }

        $event->places()->sync(array_values(array_unique($placeIds)));
    }

    protected function resolveOrganizationIdFromRequest(mixed $organizationId, ?string $organizationName): ?int
    {
        $id = $organizationId;
        if ($id === null || $id === '') {
            $id = null;
        } else {
            $id = (int) $id;
        }

        if ($id !== null) {
            $exists = Organization::query()
                ->where('id', $id)
                ->where('created_by', Auth::id())
                ->exists();

            if ($exists) {
                return $id;
            }
        }

        $name = trim((string) $organizationName);
        if ($name === '') {
            return null;
        }

        return $this->findOrCreateOrganizationForUser($name)->id;
    }

    protected function findOrCreateOrganizationForUser(string $name): Organization
    {
        $userId = Auth::id();

        $existing = Organization::query()
            ->where('created_by', $userId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Organization::create([
            'name' => $name,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function nameSuggestionsForCurrentUser(?int $exceptEventId = null): array
    {
        $query = Event::query()
            ->where('created_by', Auth::id());

        if ($exceptEventId !== null) {
            $query->where('id', '!=', $exceptEventId);
        }

        return $query
            ->whereNotNull('name')
            ->orderBy('starts_at', 'desc')
            ->limit(40)
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn ($name) => trim($name))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    protected function organizationSuggestionsForCurrentUser(): array
    {
        return Organization::query()
            ->where('created_by', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organization $org) => ['id' => $org->id, 'name' => $org->name])
            ->values()
            ->all();
    }

    public function render()
    {
        $places = Place::with(['city.translations', 'country.translations'])
            ->venues()
            ->orderBy('name')
            ->get();

        $placesUnified = $places
            ->map(function (Place $place) {
                $loc = $place->locationLabel();

                return [
                    'id' => (int) $place->id,
                    'label' => $loc !== '' ? "{$place->name} ({$loc})" : $place->name,
                    'lat' => $place->latitude !== null ? (float) $place->latitude : null,
                    'lng' => $place->longitude !== null ? (float) $place->longitude : null,
                ];
            })
            ->values()
            ->all();

        $initialNewPlaces = [];
        foreach ($this->new_places as $row) {
            if (! is_array($row)) {
                continue;
            }
            $lat = $row['latitude'] ?? null;
            $lng = $row['longitude'] ?? null;
            if ($lat === null || $lat === '' || $lng === null || $lng === '') {
                continue;
            }
            $initialNewPlaces[] = [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'name' => (string) ($row['name'] ?? ''),
                'address' => (string) ($row['address'] ?? ''),
                'city' => (string) ($row['city'] ?? ''),
                'country' => (string) ($row['country'] ?? ''),
                'city_id' => isset($row['city_id']) && $row['city_id'] !== '' ? (int) $row['city_id'] : null,
                'country_id' => isset($row['country_id']) && $row['country_id'] !== '' ? (int) $row['country_id'] : null,
            ];
        }

        $eventPlacesConfig = [
            'places' => $placesUnified,
            'initialSelectedIds' => $this->place_ids,
            'initialNewPlaces' => $initialNewPlaces,
            'searchUrl' => route('geocode.search'),
            'reverseUrl' => route('geocode.reverse'),
            'strings' => [
                'yourPlaces' => __('Your places'),
                'mapSearch' => __('Map search'),
                'noResults' => __('No results'),
                'newVenuesHeading' => __('New venues (created when you save)'),
                'newVenueNumber' => __('Venue'),
                'removeVenue' => __('Remove'),
                'addedThisForm' => __('Added on this form'),
            ],
        ];

        $exceptId = $this->editingEventId;

        return view('livewire.events.manage-event-form', [
            'tags' => Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get(),
            'nameSuggestions' => $this->nameSuggestionsForCurrentUser($exceptId),
            'organizationSuggestions' => $this->organizationSuggestionsForCurrentUser(),
            'eventPlacesConfig' => $eventPlacesConfig,
            'enforceFutureDates' => $this->editingEventId === null,
            'submitLabel' => $this->editingEventId !== null ? __('Update') : __('Create'),
            'cancelUrl' => $this->editingEventId !== null
                ? route('events.show', Event::query()->findOrFail($this->editingEventId))
                : route('events.index'),
        ]);
    }
}
