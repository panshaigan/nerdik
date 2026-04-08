<?php

namespace App\Livewire\Activities;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Tag;
use App\Services\ActivityProposalFlowService;
use App\Services\TagSelectionService;
use App\Support\RichText;
use App\Traits\AuthorizesOwnership;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public ?string $proposal_preferred_start_time = null;

    /** @var list<int> */
    public array $proposal_slot_ids = [];

    /** @var list<int> */
    public array $tag_ids = [];

    /** @var list<array{label: string, category_id: int|string}> */
    public array $new_tags = [];

    public function mount(?Activity $activity = null): void
    {
        if ($activity?->exists) {
            $this->authorizeCreatedBy($activity);
            $activity->load('tags');
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
            $this->tag_ids = $activity->tags->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $this->new_tags = [];
        } elseif (request()->filled('proposal_event_id')) {
            $id = (int) request()->query('proposal_event_id');
            if ($id > 0) {
                $this->proposal_event_id = $id;
            }
        }

        if (request()->has('proposal_slot_ids')) {
            $raw = request()->query('proposal_slot_ids');
            if (is_array($raw)) {
                $this->proposal_slot_ids = array_values(array_filter(array_map('intval', $raw)));
            } elseif (is_string($raw) && $raw !== '') {
                $this->proposal_slot_ids = array_values(array_filter(array_map('intval', explode(',', $raw))));
            }
        }
    }

    public function updatedProposalEventId(mixed $value): void
    {
        if ($value === '' || $value === null || (int) $value === 0) {
            $this->proposal_slot_ids = [];
        }
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

    public function save(TagSelectionService $tagSelectionService)
    {
        $validated = $this->validate($this->rules());

        $validated['description'] = $this->normalizeDesc($validated['description'] ?? null);
        $validated['requires_approval'] = (bool) ($validated['requires_approval'] ?? false);
        $validated['allows_observers'] = (bool) ($validated['allows_observers'] ?? false);
        $validated['is_host_passive'] = (bool) ($validated['is_host_passive'] ?? false);

        $payload = Arr::except(
            $validated,
            ['proposal_event_id', 'proposal_preferred_start_time', 'proposal_slot_ids', 'tag_ids', 'new_tags']
        );

        $tagIds = $tagSelectionService->resolveFinalTagIds(
            $this->tag_ids,
            $this->new_tags
        );

        if ($this->editingActivityId !== null) {
            $activity = Activity::query()->findOrFail($this->editingActivityId);
            $this->authorizeCreatedBy($activity);
            $activity->loadMissing('slot.activityTypes');
            $slot = $activity->slot;
            if ($slot !== null) {
                $merged = $activity->replicate();
                $merged->fill($payload);
                $slot->loadMissing('activityTypes');
                if (! $slot->fitsProposalActivity($merged)) {
                    throw ValidationException::withMessages([
                        'max_participants' => [__('ui.activities.activity_no_longer_fits_assigned_slot')],
                    ]);
                }
            }
            $activity->update($payload);
            $activity->tags()->sync($tagIds);
            $proposalCreated = $this->createProposalForActivityIfRequested($activity);
            $message = $proposalCreated
                ? __('ui.status.activity_updated_with_proposal', ['event' => $proposalCreated->event->name])
                : __('Activity updated.');
        } else {
            $activity = Activity::create($payload);
            $activity->tags()->sync($tagIds);
            $proposalCreated = $this->createProposalForActivityIfRequested($activity);
            $message = $proposalCreated
                ? __('ui.status.activity_saved_with_proposal', ['event' => $proposalCreated->event->name])
                : __('Activity created.');
        }

        session()->flash('status', $message);

        if ($proposalCreated !== null) {
            return redirect()->route('events.show', $proposalCreated->event);
        }

        if ($this->editingActivityId !== null) {
            return redirect()->route('activities.show', $activity);
        }

        return redirect()->route('search.index');
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

    protected function normalizeDesc(?string $html): ?string
    {
        return RichText::sanitize($html);
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

    protected function createProposalForActivityIfRequested(Activity $activity): ?ActivityProposal
    {
        if ($this->proposal_event_id === null || $this->proposal_event_id === 0) {
            return null;
        }

        $event = Event::findOrFail($this->proposal_event_id);

        $preferred = $this->proposal_preferred_start_time;

        $proposal = ActivityProposal::create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => auth()->id(),
            'preferred_start_time' => $preferred !== null && $preferred !== '' ? $preferred : null,
            'status' => ActivityProposalStatus::Pending,
        ]);
        $proposal->load(['activity', 'event', 'creator']);

        $flow = app(ActivityProposalFlowService::class);
        $flow->notifyHostOfNewProposal($proposal);
        $flow->attachProposedSlotsAndTryAutoAccept($proposal, $event, $activity, $this->proposal_slot_ids);

        return $proposal;
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

        return view('livewire.activities.manage-activity-form', [
            'tags' => Tag::orderedForSelector()->get(),
            'futureEvents' => $this->futureEventsForProposal(),
            'nameSuggestions' => $this->nameSuggestionsForCurrentUser($exceptId),
            'proposalEventSlots' => $proposalEventSlots,
            'activityTypes' => ActivityType::query()->orderBy('id')->get(),
        ]);
    }
}
