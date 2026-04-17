<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Place;
use App\Models\Tag;
use App\Services\ActivityFormService;
use App\Services\ActivityHostingModeService;
use App\Services\LocationResolver;
use App\Services\TagSelectionService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ManageActivityForm extends Component
{
    use AuthorizesOwnership;

    private const NAME_SUGGESTIONS_LIMIT = 40;

    public ?int $editingActivityId = null;

    public string $name = '';

    public string $description = '';

    public ?int $activity_type_id = null;

    public ?int $min_participants = null;

    public ?int $max_participants = null;

    public ?int $minimum_age = null;

    public ?int $duration_in_minutes = null;

    public ?int $cancellation_deadline_in_hours = null;

    public bool $is_host_passive = false;

    public bool $requires_approval = false;

    public bool $allows_observers = false;

    public ?int $proposal_event_id = null;

    public int $hosting_mode = Activity::HOSTING_MODE_DRAFT;

    public ?string $self_hosted_starts_at = null;

    public ?int $self_hosted_venue_place_id = null;

    public ?string $self_hosted_room_name = null;

    public ?int $self_hosted_place_id = null;

    /** @var list<int> */
    public array $place_ids = [];

    /** @var list<array<string, mixed>> */
    public array $new_places = [];

    public ?string $proposal_preferred_start_time = null;

    /** @var list<int> */
    public array $proposal_slot_ids = [];

    /** @var list<int> */
    public array $tag_ids = [];

    /** @var list<array{label: string, category_id: int|string}> */
    public array $new_tags = [];

    /** @see updatedPlaceIds() avoids clearing room when map debounce re-sends the same selection */
    private ?string $cachedSelfHostedPlaceIdsFingerprint = null;

    /** @see updatedNewPlaces() */
    private ?string $cachedSelfHostedNewPlacesFingerprint = null;

    public function mount(?Activity $activity = null): void
    {
        if ($activity?->exists) {
            $this->authorizeCreatedBy($activity);
            $activity->load(['tags', 'place.parent']);
            $this->editingActivityId = $activity->id;
            $this->name = (string) $activity->name;
            $this->description = (string) ($activity->description ?? '');
            $this->activity_type_id = $activity->activity_type_id;
            $this->min_participants = $activity->min_participants;
            $this->max_participants = $activity->max_participants;
            $this->minimum_age = $activity->minimum_age;
            $this->duration_in_minutes = $activity->duration_in_minutes;
            $this->cancellation_deadline_in_hours = $activity->cancellation_deadline_in_hours;
            $this->is_host_passive = (bool) $activity->is_host_passive;
            $this->requires_approval = (bool) $activity->requires_approval;
            $this->allows_observers = (bool) $activity->allows_observers;
            $this->hosting_mode = (int) ($activity->hosting_mode ?: Activity::HOSTING_MODE_DRAFT);
            $this->self_hosted_place_id = $activity->place_id;
            $selfHostedPlace = $activity->place;
            if ($selfHostedPlace?->type === 'room') {
                $this->self_hosted_venue_place_id = $selfHostedPlace->parent_id;
                $this->self_hosted_room_name = $selfHostedPlace->name;
            } elseif ($selfHostedPlace?->type === 'venue') {
                $this->self_hosted_venue_place_id = $selfHostedPlace->id;
                $this->self_hosted_room_name = null;
            }
            if ($this->self_hosted_venue_place_id !== null) {
                $this->place_ids = [$this->self_hosted_venue_place_id];
            }
            $this->self_hosted_starts_at = format_in_user_tz($activity->starts_at, 'Y-m-d\TH:i');
            $this->tag_ids = $activity->tags->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $this->new_tags = [];
        } elseif (($dupSlug = $this->duplicateQuerySlug()) !== null) {
            $source = Activity::query()->with('tags')->where('slug', $dupSlug)->first();
            if ($source !== null) {
                $this->applyDuplicatePrefillFromActivity($source);
            }
        } elseif (request()->filled('proposal_event_id')) {
            $id = (int) request()->query('proposal_event_id');
            if ($id > 0) {
                $this->proposal_event_id = $id;
            }
        }

        if ($this->duplicateQuerySlug() === null && request()->has('proposal_slot_ids')) {
            $raw = request()->query('proposal_slot_ids');
            if (is_array($raw)) {
                $this->proposal_slot_ids = array_values(array_filter(array_map('intval', $raw)));
            } elseif (is_string($raw) && $raw !== '') {
                $this->proposal_slot_ids = array_values(array_filter(array_map('intval', explode(',', $raw))));
            }
        }

        $this->resetSelfHostedRoomTrackingFingerprints();
    }

    private function duplicateQuerySlug(): ?string
    {
        $raw = request()->query('duplicate');
        if (! is_string($raw)) {
            return null;
        }
        $trim = trim($raw);

        return $trim === '' ? null : $trim;
    }

    /**
     * Copy content/options fields only; hosting and proposal fields stay at create defaults.
     */
    private function applyDuplicatePrefillFromActivity(Activity $source): void
    {
        $suffix = __('ui.activities.duplicate_name_suffix');
        $base = (string) $source->name;
        $maxBase = max(0, 255 - mb_strlen($suffix));
        $this->name = mb_substr($base, 0, $maxBase).$suffix;
        $this->description = (string) ($source->description ?? '');
        $this->activity_type_id = $source->activity_type_id;
        $this->min_participants = $source->min_participants;
        $this->max_participants = $source->max_participants;
        $this->minimum_age = $source->minimum_age;
        $this->duration_in_minutes = $source->duration_in_minutes;
        $this->cancellation_deadline_in_hours = $source->cancellation_deadline_in_hours;
        $this->is_host_passive = (bool) $source->is_host_passive;
        $this->requires_approval = (bool) $source->requires_approval;
        $this->allows_observers = (bool) $source->allows_observers;
        $this->tag_ids = $source->tags->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $this->new_tags = [];

        $this->hosting_mode = Activity::HOSTING_MODE_DRAFT;
        $this->proposal_event_id = null;
        $this->proposal_preferred_start_time = null;
        $this->proposal_slot_ids = [];
        $this->self_hosted_starts_at = null;
        $this->self_hosted_venue_place_id = null;
        $this->self_hosted_room_name = null;
        $this->self_hosted_place_id = null;
        $this->place_ids = [];
        $this->new_places = [];
    }

    public function updatedProposalEventId(mixed $value): void
    {
        if ($value === '' || $value === null || (int) $value === 0) {
            $this->proposal_slot_ids = [];
        }
    }

    public function updatedHostingMode(): void
    {
        $this->resetSelfHostedRoomTrackingFingerprints();
    }

    public function updatedPlaceIds(): void
    {
        if ($this->hosting_mode !== Activity::HOSTING_MODE_SELF_HOSTED) {
            $this->resetSelfHostedRoomTrackingFingerprints();

            return;
        }

        $fp = $this->computeSelfHostedPlaceIdsFingerprint();
        if ($fp === $this->cachedSelfHostedPlaceIdsFingerprint) {
            return;
        }

        $this->cachedSelfHostedPlaceIdsFingerprint = $fp;
        $this->self_hosted_room_name = null;
    }

    public function updatedNewPlaces(): void
    {
        if ($this->hosting_mode !== Activity::HOSTING_MODE_SELF_HOSTED) {
            $this->resetSelfHostedRoomTrackingFingerprints();

            return;
        }

        $fp = $this->computeSelfHostedNewPlacesFingerprint();
        if ($fp === $this->cachedSelfHostedNewPlacesFingerprint) {
            return;
        }

        $this->cachedSelfHostedNewPlacesFingerprint = $fp;
        $this->self_hosted_room_name = null;
    }

    private function resetSelfHostedRoomTrackingFingerprints(): void
    {
        $this->cachedSelfHostedPlaceIdsFingerprint = $this->computeSelfHostedPlaceIdsFingerprint();
        $this->cachedSelfHostedNewPlacesFingerprint = $this->computeSelfHostedNewPlacesFingerprint();
    }

    private function computeSelfHostedPlaceIdsFingerprint(): string
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $this->place_ids),
            fn (int $id) => $id > 0
        )));
        sort($ids);

        return implode(',', $ids);
    }

    private function computeSelfHostedNewPlacesFingerprint(): string
    {
        $raw = json_encode($this->new_places) ?: '[]';

        return md5($raw);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function prepareForValidation($attributes)
    {
        foreach (['min_participants', 'max_participants', 'minimum_age', 'duration_in_minutes', 'cancellation_deadline_in_hours'] as $key) {
            if ($this->{$key} === '') {
                $this->{$key} = null;
            }
        }

        if ($this->proposal_event_id === '' || $this->proposal_event_id === 0) {
            $this->proposal_event_id = null;
        } elseif ($this->proposal_event_id !== null) {
            $this->proposal_event_id = (int) $this->proposal_event_id;
        }

        if ($this->proposal_preferred_start_time === '') {
            $this->proposal_preferred_start_time = null;
        }
        if ($this->hosting_mode === 0 || $this->hosting_mode === '') {
            $this->hosting_mode = Activity::HOSTING_MODE_DRAFT;
        } else {
            $this->hosting_mode = (int) $this->hosting_mode;
        }
        if ($this->hosting_mode !== Activity::HOSTING_MODE_PROPOSED_TO_EVENT) {
            $this->proposal_event_id = null;
            $this->proposal_preferred_start_time = null;
            $this->proposal_slot_ids = [];
        }
        if ($this->self_hosted_starts_at === '') {
            $this->self_hosted_starts_at = null;
        }
        if ($this->self_hosted_place_id === 0 || $this->self_hosted_place_id === '') {
            $this->self_hosted_place_id = null;
        }
        if ($this->self_hosted_venue_place_id === 0 || $this->self_hosted_venue_place_id === '') {
            $this->self_hosted_venue_place_id = null;
        }
        if ($this->self_hosted_room_name !== null) {
            $this->self_hosted_room_name = trim($this->self_hosted_room_name);
            if ($this->self_hosted_room_name === '') {
                $this->self_hosted_room_name = null;
            }
        }
        $this->place_ids = array_values(array_unique(array_filter(array_map('intval', $this->place_ids), fn (int $id) => $id > 0)));
        if (count($this->place_ids) > 1) {
            $this->place_ids = [(int) $this->place_ids[0]];
        }
        if ($this->hosting_mode === Activity::HOSTING_MODE_SELF_HOSTED && $this->new_places !== []) {
            // When a new venue is being created from the map, ignore stale existing venue selection.
            $this->self_hosted_venue_place_id = null;
            $this->place_ids = [];
        }
        if ($this->hosting_mode === Activity::HOSTING_MODE_SELF_HOSTED && $this->self_hosted_venue_place_id === null && $this->place_ids !== []) {
            $this->self_hosted_venue_place_id = $this->place_ids[0];
        }

        $this->proposal_slot_ids = array_values(array_unique(array_filter(
            array_map('intval', $this->proposal_slot_ids),
            fn (int $id) => $id > 0
        )));

        foreach (array_keys($attributes) as $key) {
            if (property_exists($this, $key)) {
                $attributes[$key] = $this->{$key};
            }
        }

        return $attributes;
    }

    public function clearNumericFields(): void
    {
        $this->min_participants = null;
        $this->max_participants = null;
        $this->minimum_age = null;
        $this->duration_in_minutes = null;
        $this->cancellation_deadline_in_hours = null;
    }

    public function save(
        TagSelectionService $tagSelectionService,
        ActivityHostingModeService $hostingModes,
        LocationResolver $locationResolver,
        ActivityFormService $activityForm,
    ) {
        $validated = $this->validate($this->rules());

        if ($this->editingActivityId !== null) {
            $activity = Activity::query()->findOrFail($this->editingActivityId);
            $this->authorizeCreatedBy($activity);
        }

        return $activityForm->persist($this, $validated, $tagSelectionService, $hostingModes, $locationResolver);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'activity_type_id' => ['required', 'integer', 'exists:activity_types,id'],
            'min_participants' => [
                'nullable',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $max = $this->max_participants;
                    if ($max !== null && $max !== '' && (int) $value > (int) $max) {
                        $fail(__('ui.activities.min_participants_lte_max'));
                    }
                },
            ],
            'max_participants' => [
                'nullable',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $min = $this->min_participants;
                    if ($min !== null && $min !== '' && (int) $value < (int) $min) {
                        $fail(__('ui.activities.max_participants_gte_min'));
                    }
                },
            ],
            'minimum_age' => ['nullable', 'integer', 'min:0'],
            'duration_in_minutes' => ['nullable', Rule::numeric()->integer()->min(0)->multipleOf(5)],
            'cancellation_deadline_in_hours' => ['nullable', 'integer', 'min:0'],
            'requires_approval' => ['nullable', 'boolean'],
            'allows_observers' => ['nullable', 'boolean'],
            'is_host_passive' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'array'],
            'new_tags.*.label' => ['nullable', 'string', 'max:255'],
            'new_tags.*.category_id' => ['nullable', 'integer', 'exists:tag_categories,id'],
            'hosting_mode' => ['required', Rule::in(Activity::hostingModes())],
            'self_hosted_starts_at' => ['nullable', 'date'],
            'self_hosted_venue_place_id' => ['nullable', 'integer', Rule::exists('places', 'id')->where(fn ($q) => $q->where('type', 'venue'))],
            'self_hosted_room_name' => ['nullable', 'string', 'max:255'],
            'self_hosted_place_id' => ['nullable', 'integer', 'exists:places,id'],
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
            ...$this->proposalFieldValidationRules(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function proposalFieldValidationRules(): array
    {
        return [
            'proposal_event_id' => [
                'nullable',
                'integer',
                Rule::exists('events', 'id')->where(function ($query) {
                    $query->where(function ($q) {
                        $q->where('ends_at', '>', now())
                            ->orWhere(function ($q2) {
                                $q2->whereNull('ends_at')
                                    ->whereNotNull('starts_at')
                                    ->where('starts_at', '>', now());
                            });
                    });
                }),
            ],
            'proposal_preferred_start_time' => ['nullable', 'date'],
            'proposal_slot_ids' => ['nullable', 'array'],
            'proposal_slot_ids.*' => [
                'integer',
                Rule::exists('slots', 'id')->where(function ($query) {
                    if ($this->proposal_event_id === null || $this->proposal_event_id === 0) {
                        $query->whereRaw('1 = 0');

                        return;
                    }
                    $query->where('event_id', $this->proposal_event_id)->whereNull('activity_id');
                }),
            ],
        ];
    }

    /**
     * @return Collection<int, Event>
     */
    protected function futureEventsForProposal(): Collection
    {
        return Event::query()
            ->where(function ($q) {
                $q->where('ends_at', '>', now())
                    ->orWhere(function ($q2) {
                        $q2->whereNull('ends_at')
                            ->whereNotNull('starts_at')
                            ->where('starts_at', '>', now());
                    });
            })
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return list<string>
     */
    protected function nameSuggestionsForCurrentUser(?int $exceptActivityId = null): array
    {
        $query = Activity::query()
            ->where('created_by', auth()->id());

        if ($exceptActivityId !== null) {
            $query->where('id', '!=', $exceptActivityId);
        }

        return $query
            ->whereNotNull('name')
            ->orderBy('created_at', 'desc')
            ->limit(self::NAME_SUGGESTIONS_LIMIT)
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn ($name) => trim($name))
            ->unique()
            ->values()
            ->all();
    }

    public function render()
    {
        $exceptId = $this->editingActivityId;

        $proposalEventSlots = collect();
        if ($this->proposal_event_id) {
            $proposalEventSlots = Event::query()
                ->whereKey($this->proposal_event_id)
                ->first()
                ?->slots()
                ->orderBy('starts_at')
                ->get() ?? collect();
        }

        $venues = Place::query()
            ->where('type', 'venue')
            ->orderBy('name')
            ->get();
        $placesUnified = $venues
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
        $selfHostedPlacesConfig = [
            'places' => $placesUnified,
            'initialSelectedIds' => $this->self_hosted_venue_place_id
                ? [(int) $this->self_hosted_venue_place_id]
                : ($this->place_ids !== [] ? [(int) $this->place_ids[0]] : []),
            'initialNewPlaces' => $this->new_places,
            'searchUrl' => route('geocode.search'),
            'reverseUrl' => route('geocode.reverse'),
            'singleSelect' => true,
            'maxNewVenues' => 1,
            'disallowMixSelectedAndNew' => true,
            'debounceLivewireMs' => 450,
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
        $roomsFetchUrlTemplate = url('/places/__PLACE__/rooms');

        return view('livewire.activities.manage-activity-form', [
            'tags' => Tag::orderedForSelector()->get(),
            'futureEvents' => $this->futureEventsForProposal(),
            'selfHostedPlacesConfig' => $selfHostedPlacesConfig,
            'roomsFetchUrlTemplate' => $roomsFetchUrlTemplate,
            'nameSuggestions' => $this->nameSuggestionsForCurrentUser($exceptId),
            'proposalEventSlots' => $proposalEventSlots,
            'activityTypes' => ActivityType::query()->orderBy('id')->get(),
        ]);
    }
}
