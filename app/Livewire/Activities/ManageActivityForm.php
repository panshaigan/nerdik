<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Models\Tag;
use App\Notifications\ProposalAcceptedNotification;
use App\Notifications\ProposalSubmittedNotification;
use App\Services\TagSelectionService;
use App\Support\ActivityTypes;
use App\Support\RichText;
use App\Traits\AuthorizesOwnership;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ManageActivityForm extends Component
{
    use AuthorizesOwnership;

    public ?int $editingActivityId = null;

    public string $name = '';

    public string $desc = '';

    public string $type = '';

    public ?int $min_participants = null;

    public ?int $max_participants = null;

    public ?int $age_limit = null;

    public ?int $duration_minutes = null;

    public ?int $signoff_deadline_hours = null;

    public bool $passive_host = false;

    public bool $is_restricted = false;

    public bool $open_for_observers = false;

    public ?int $proposal_event_id = null;

    public ?string $proposal_preferred_start_time = null;

    /** @var list<int> */
    public array $proposal_slot_ids = [];

    /** @var list<int> */
    public array $tag_ids = [];

    /** @var list<array{label: string, category: string}> */
    public array $new_tags = [];

    public function mount(?Activity $activity = null): void
    {
        if ($activity?->exists) {
            $this->authorizeCreatedBy($activity);
            $activity->load('tags');
            $this->editingActivityId = $activity->id;
            $this->name = (string) $activity->name;
            $this->desc = (string) ($activity->desc ?? '');
            $this->type = (string) $activity->type;
            $this->min_participants = $activity->min_participants;
            $this->max_participants = $activity->max_participants;
            $this->age_limit = $activity->age_limit;
            $this->duration_minutes = $activity->duration_minutes;
            $this->signoff_deadline_hours = $activity->signoff_deadline_hours;
            $this->passive_host = (bool) $activity->passive_host;
            $this->is_restricted = (bool) $activity->is_restricted;
            $this->open_for_observers = (bool) $activity->open_for_observers;
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
        foreach (['min_participants', 'max_participants', 'age_limit', 'duration_minutes', 'signoff_deadline_hours'] as $key) {
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
        $this->age_limit = null;
        $this->duration_minutes = null;
        $this->signoff_deadline_hours = null;
    }

    public function save(TagSelectionService $tagSelectionService)
    {
        $validated = $this->validate($this->rules());

        $validated['desc'] = $this->normalizeDesc($validated['desc'] ?? null);
        $validated['is_restricted'] = (bool) ($validated['is_restricted'] ?? false);
        $validated['open_for_observers'] = (bool) ($validated['open_for_observers'] ?? false);
        $validated['passive_host'] = (bool) ($validated['passive_host'] ?? false);

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

        return redirect()->route('activities.index');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in(ActivityTypes::VALUES)],
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
            'age_limit' => ['nullable', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', Rule::numeric()->integer()->min(0)->multipleOf(5)],
            'signoff_deadline_hours' => ['nullable', 'integer', 'min:0'],
            'is_restricted' => ['nullable', 'boolean'],
            'open_for_observers' => ['nullable', 'boolean'],
            'passive_host' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'array'],
            'new_tags.*.label' => ['nullable', 'string', 'max:255'],
            'new_tags.*.category' => ['nullable', Rule::in(TagSelectionService::CATEGORY_OPTIONS)],
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
            'status' => 'pending',
        ]);
        $proposal->load(['activity', 'event', 'creator']);

        if ($event->created_by !== auth()->id()) {
            $event->creator?->notify(new ProposalSubmittedNotification($proposal));
        }

        if (! empty($this->proposal_slot_ids)) {
            $validIds = Slot::query()
                ->where('event_id', $event->id)
                ->whereNull('activity_id')
                ->whereIn('id', $this->proposal_slot_ids)
                ->pluck('id')
                ->all();
            $proposal->proposedSlots()->sync($validIds);

            $slots = Slot::whereIn('id', $validIds)->get();
            $autoSlot = $slots->firstWhere('requires_approval', false);
            if ($autoSlot) {
                $proposal->update([
                    'status' => 'accepted',
                    'accepted_slot_id' => $autoSlot->id,
                ]);
                $autoSlot->update(['activity_id' => $activity->id]);

                $proposal->creator?->notify(new ProposalAcceptedNotification($proposal->fresh(['activity', 'event'])));
            }
        }

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
            ->limit(40)
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
            'tags' => Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get(),
            'futureEvents' => $this->futureEventsForProposal(),
            'nameSuggestions' => $this->nameSuggestionsForCurrentUser($exceptId),
            'proposalEventSlots' => $proposalEventSlots,
        ]);
    }
}
