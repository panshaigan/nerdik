<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Tag;
use App\Services\TagSelectionService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    use AuthorizesOwnership;

    /** @var list<string> */
    public const ACTIVITY_TYPES = ['rpg', 'board', 'card', 'larp', 'lecture', 'workshop', 'competition', 'show'];

    public function __construct(
        private readonly TagSelectionService $tagSelectionService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $activities = Activity::with('host')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('activities.index', compact('activities'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $tags = Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get();
        $nameSuggestions = $this->nameSuggestionsForCurrentUser();

        return view('activities.create', [
            'activity' => new Activity,
            'tags' => $tags,
            'nameSuggestions' => $nameSuggestions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $this->validateData($request);
        $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'array'],
            'new_tags.*.label' => ['nullable', 'string', 'max:255'],
            'new_tags.*.category' => ['nullable', Rule::in(TagSelectionService::CATEGORY_OPTIONS)],
        ]);

        $tagIds = $this->tagSelectionService->resolveFinalTagIds(
            (array) $request->input('tag_ids', []),
            (array) $request->input('new_tags', [])
        );

        $activity = Activity::create($validated);
        $activity->tags()->sync($tagIds);

        return redirect()->route('activities.index')
            ->with('status', __('Activity created.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Activity $activity)
    {
        $activity->load(['host', 'creator', 'tags.translations', 'participants.user', 'waitlist.user']);
        $isParticipant = auth()->check() && $activity->participants()->where('user_id', auth()->id())->exists();
        $onWaitlist = auth()->check() && $activity->waitlist()->where('user_id', auth()->id())->exists();
        $canJoin = auth()->check() && ! $isParticipant && ! $onWaitlist;
        $isFull = $activity->max_participants !== null && $activity->participants()->count() >= $activity->max_participants;
        $isHost = auth()->check() && $activity->host_user_id === auth()->id();
        $inWishlist = auth()->check() && auth()->user()->wishlistActivities()->where('activities.id', $activity->id)->exists();

        return view('activities.show', compact('activity', 'isParticipant', 'onWaitlist', 'canJoin', 'isFull', 'isHost', 'inWishlist'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Activity $activity)
    {
        $this->authorizeCreatedBy($activity);

        $tags = Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get();
        $activity->load('tags');
        $nameSuggestions = $this->nameSuggestionsForCurrentUser($activity->id);

        return view('activities.edit', [
            'activity' => $activity,
            'tags' => $tags,
            'nameSuggestions' => $nameSuggestions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Activity $activity)
    {
        $this->authorizeCreatedBy($activity);

        $validated = $this->validateData($request, $activity);
        $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'array'],
            'new_tags.*.label' => ['nullable', 'string', 'max:255'],
            'new_tags.*.category' => ['nullable', Rule::in(TagSelectionService::CATEGORY_OPTIONS)],
        ]);

        $tagIds = $this->tagSelectionService->resolveFinalTagIds(
            (array) $request->input('tag_ids', []),
            (array) $request->input('new_tags', [])
        );

        $activity->update($validated);
        $activity->tags()->sync($tagIds);

        return redirect()->route('activities.index')
            ->with('status', __('Activity updated.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity)
    {
        $this->authorizeCreatedBy($activity);

        $activity->delete();

        return redirect()->route('activities.index')
            ->with('status', __('Activity deleted.'));
    }

    protected function validateData(Request $request, ?Activity $activity = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in(self::ACTIVITY_TYPES)],
            'min_participants' => [
                'nullable',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $max = $request->input('max_participants');
                    if ($max !== null && $max !== '' && (int) $value > (int) $max) {
                        $fail(__('ui.activities.min_participants_lte_max'));
                    }
                },
            ],
            'max_participants' => [
                'nullable',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $min = $request->input('min_participants');
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
        ]);

        $validated['is_restricted'] = $request->boolean('is_restricted');
        $validated['open_for_observers'] = $request->boolean('open_for_observers');
        $validated['passive_host'] = $request->boolean('passive_host');
        $validated['host_user_id'] = auth()->id();

        return $validated;
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
}
