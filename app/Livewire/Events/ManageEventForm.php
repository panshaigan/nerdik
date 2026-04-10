<?php

namespace App\Livewire\Events;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Services\EventActivitySignupService;
use App\Services\EventEmptySlotCloneService;
use App\Services\LocationResolver;
use App\Support\RichText;
use App\Traits\AuthorizesOwnership;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ManageEventForm extends Component
{
    use AuthorizesOwnership;

    private const NAME_SUGGESTIONS_LIMIT = 40;

    public ?int $editingEventId = null;

    public string $name = '';

    public string $description = '';

    public ?int $organization_id = null;

    public string $organization_name = '';

    public bool $is_public = true;

    public string $starts_at = '';

    public string $ends_at = '';

    /** @var list<int> */
    public array $place_ids = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $new_places = [];

    /**
     * Enrollment windows for this event (local datetime-local strings + optional caps/mode).
     *
     * @var list<array{
     *   starts_at: string,
     *   ends_at: string,
     *   max_activities_per_user: int|string|null,
     *   max_allowed_participants_per_activity: int|string|null,
     *   accumulative_activities: bool
     * }>
     */
    public array $enrollment_windows = [];

    /** When set, creating the event will clone empty slots from this source (owner-only; re-checked on save). */
    public ?int $duplicateSlotsFromEventId = null;

    public function mount(?Event $event = null): void
    {
        if ($event?->exists) {
            $this->authorizeCreatedBy($event);
            $this->hydrateFormFromEvent($event, forEdit: true);
        } elseif (($dupSlug = $this->duplicateEventQuerySlug()) !== null) {
            $source = Event::query()->where('slug', $dupSlug)->first();
            if ($source !== null) {
                $this->authorizeCreatedBy($source);
                $this->hydrateFormFromEvent($source, forEdit: false, forDuplicate: true);
                $this->duplicateSlotsFromEventId = $source->id;
            }
        }

        if ($this->enrollment_windows === []) {
            $this->enrollment_windows = [$this->defaultEnrollmentWindowRow()];
        }
    }

    private function duplicateEventQuerySlug(): ?string
    {
        $raw = request()->query('duplicate');
        if (! is_string($raw)) {
            return null;
        }
        $trim = trim($raw);

        return $trim === '' ? null : $trim;
    }

    private function hydrateFormFromEvent(Event $event, bool $forEdit, bool $forDuplicate = false): void
    {
        $event->load(['places', 'organization', 'enrollmentWindows']);
        if ($forEdit) {
            $this->editingEventId = $event->id;
        }
        $name = (string) $event->name;
        if ($forDuplicate) {
            $suffix = __('ui.events.duplicate_name_suffix');
            $maxBase = max(0, 255 - mb_strlen($suffix));
            $name = mb_substr($name, 0, $maxBase).$suffix;
        }
        $this->name = $name;
        $this->description = (string) ($event->description ?? '');
        $this->organization_id = $event->organization_id;
        $this->organization_name = (string) (optional($event->organization)->name ?? '');
        $this->is_public = (bool) $event->is_public;
        $this->starts_at = $event->starts_at ? format_in_user_tz($event->starts_at, 'Y-m-d\TH:i') : '';
        $this->ends_at = $event->ends_at ? format_in_user_tz($event->ends_at, 'Y-m-d\TH:i') : '';
        $this->place_ids = $event->places
            ->filter(fn (Place $p) => $p->type === 'venue')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $this->new_places = [];
        $this->enrollment_windows = $event->enrollmentWindows
            ->map(fn ($p) => [
                'starts_at' => format_in_user_tz($p->starts_at, 'Y-m-d\TH:i'),
                'ends_at' => format_in_user_tz($p->ends_at, 'Y-m-d\TH:i'),
                'max_activities_per_user' => $p->max_activities_per_user,
                'max_allowed_participants_per_activity' => $p->max_allowed_participants_per_activity,
                'accumulative_activities' => (bool) $p->accumulative_activities,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   starts_at: string,
     *   ends_at: string,
     *   max_activities_per_user: int|string|null,
     *   max_allowed_participants_per_activity: int|string|null,
     *   accumulative_activities: bool
     * }
     */
    protected function defaultEnrollmentWindowRow(): array
    {
        $starts = format_in_user_tz(Carbon::now(), 'Y-m-d\TH:i');
        $ends = $this->ends_at !== '' && $this->ends_at !== null
            ? $this->ends_at
            : '';

        return [
            'starts_at' => $starts,
            'ends_at' => $ends,
            'max_activities_per_user' => null,
            'max_allowed_participants_per_activity' => null,
            'accumulative_activities' => false,
        ];
    }

    public function addEnrollmentWindow(): void
    {
        $this->enrollment_windows[] = [
            'starts_at' => '',
            'ends_at' => '',
            'max_activities_per_user' => null,
            'max_allowed_participants_per_activity' => null,
            'accumulative_activities' => false,
        ];
    }

    public function removeEnrollmentWindow(int $index): void
    {
        unset($this->enrollment_windows[$index]);
        $this->enrollment_windows = array_values($this->enrollment_windows);
        if ($this->enrollment_windows === []) {
            $this->enrollment_windows = [$this->defaultEnrollmentWindowRow()];
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

        foreach ($this->enrollment_windows as $i => $row) {
            $m = $row['max_activities_per_user'] ?? null;
            if ($m === '' || $m === null) {
                $this->enrollment_windows[$i]['max_activities_per_user'] = null;
            }
            $perActivity = $row['max_allowed_participants_per_activity'] ?? null;
            if ($perActivity === '' || $perActivity === null) {
                $this->enrollment_windows[$i]['max_allowed_participants_per_activity'] = null;
            }
            $this->enrollment_windows[$i]['accumulative_activities'] = (bool) ($row['accumulative_activities'] ?? false);
        }

        return $attributes;
    }

    public function save(LocationResolver $locationResolver, EventActivitySignupService $signupService, EventEmptySlotCloneService $slotCloneService)
    {
        $validated = $this->validate($this->rules());

        $validated['description'] = $this->normalizeDesc($validated['description'] ?? null);
        $validated['is_public'] = (bool) ($validated['is_public'] ?? true);

        $validated['starts_at'] = parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString();
        $validated['ends_at'] = parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString();

        $orgName = isset($validated['organization_name']) ? trim((string) $validated['organization_name']) : '';
        $validated['organization_id'] = $this->resolveOrganizationIdFromRequest(
            $validated['organization_id'] ?? null,
            $orgName !== '' ? $orgName : null
        );
        unset($validated['organization_name']);

        $placeIds = $this->place_ids;
        unset($validated['place_ids'], $validated['new_places']);

        unset($validated['slug']);

        if ($this->editingEventId !== null) {
            $event = Event::query()->findOrFail($this->editingEventId);
            $this->authorizeCreatedBy($event);
            $event->update($validated);
            $this->syncEventPlaces($event, $placeIds, $this->new_places, $locationResolver);
            $event->refresh();
            $this->syncEnrollmentWindows($event, $signupService);
            session()->flash('status', __('Event updated.'));

            return redirect()->route('events.show', $event);
        }

        abort_unless(Auth::user()?->canCreateEvents(), 403, __('ui.events.only_event_organizers_can_create'));

        $validated['created_by'] = Auth::id();
        $duplicateSlotsFrom = $this->duplicateSlotsFromEventId;

        $event = Event::create($validated);
        $this->syncEventPlaces($event, $placeIds, $this->new_places, $locationResolver);
        $event->refresh();
        $this->syncEnrollmentWindows($event, $signupService);

        if ($duplicateSlotsFrom !== null) {
            $source = Event::query()->find($duplicateSlotsFrom);
            if ($source !== null && Auth::user()?->canModifyEntity($source)) {
                $slotCloneService->cloneEmptySlots($source, $event);
            }
        }

        $this->duplicateSlotsFromEventId = null;

        session()->flash('status', __('Event created.'));

        return redirect()->route('search.index');
    }

    protected function syncEnrollmentWindows(Event $event, EventActivitySignupService $signupService): void
    {
        $normalized = $signupService->validateAndNormalizePeriodRowsForEvent(
            $event,
            $this->enrollment_windows,
            Carbon::now('UTC')
        );

        $event->enrollmentWindows()->delete();
        foreach ($normalized as $row) {
            $event->enrollmentWindows()->create([
                'starts_at' => $row['starts_at']->toDateTimeString(),
                'ends_at' => $row['ends_at']->toDateTimeString(),
                'max_activities_per_user' => $row['max_activities_per_user'],
                'max_allowed_participants_per_activity' => $row['max_allowed_participants_per_activity'],
                'accumulative_activities' => $row['accumulative_activities'],
            ]);
        }
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
            'description' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
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
            ->limit(self::NAME_SUGGESTIONS_LIMIT)
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
            'nameSuggestions' => $this->nameSuggestionsForCurrentUser($exceptId),
            'organizationSuggestions' => $this->organizationSuggestionsForCurrentUser(),
            'eventPlacesConfig' => $eventPlacesConfig,
            'enforceFutureDates' => $this->editingEventId === null,
            'submitLabel' => $this->editingEventId !== null ? __('Update') : __('Create'),
            'cancelUrl' => $this->editingEventId !== null
                ? route('events.show', Event::query()->findOrFail($this->editingEventId))
                : route('search.index'),
            'eventSignupPeriodMax' => $this->ends_at !== '' ? $this->ends_at : null,
        ]);
    }
}
