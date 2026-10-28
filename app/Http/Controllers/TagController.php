<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Services\ActivitySuggestedBringService;
use App\Services\TagService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Http\Request;

class TagController extends Controller
{
    use AuthorizesOwnership;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tags = Tag::with('translations')
            ->with('tagCategory.translations')
            ->orderBy('tag_category_id')
            ->get();

        return view('tags.index', compact('tags'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(TagService $tags)
    {
        return view('tags.create', [
            'tag' => new Tag,
            'labelEn' => '',
            'labelPl' => '',
            'categoryOptions' => $tags->categoryOptions(),
            'suggestedBringRows' => [],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, TagService $tags)
    {
        $validated = $request->validate([
            'tag_category_id' => ['required', 'integer', 'exists:tag_categories,id'],
            'label_en' => ['nullable', 'string', 'max:255'],
            'label_pl' => ['nullable', 'string', 'max:255'],
            'suggested_bring' => ['nullable', 'array'],
            'suggested_bring.*.label_en' => ['nullable', 'string', 'max:255'],
            'suggested_bring.*.label_pl' => ['nullable', 'string', 'max:255'],
        ]);

        $tag = $tags->createFromValidated($validated);
        $this->syncTagBringLabels($tag, $validated);

        return redirect()->route('tags.index')
            ->with('status', __('Tag created.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Tag $tag)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tag $tag, TagService $tags)
    {
        $this->authorizeCreatedBy($tag);

        $tag->loadMissing('translations');
        $labelEn = $tag->translations->firstWhere('locale', 'en')?->label ?? '';
        $labelPl = $tag->translations->firstWhere('locale', 'pl')?->label ?? '';
        $tag->loadMissing(['suggestedBringItems.translations']);
        $suggestedBringRows = $tag->suggestedBringItems
            ->map(function ($item): array {
                $item->loadMissing('translations');

                return [
                    'label_en' => (string) ($item->translations->firstWhere('locale', 'en')?->label ?? ''),
                    'label_pl' => (string) ($item->translations->firstWhere('locale', 'pl')?->label ?? ''),
                ];
            })
            ->values()
            ->all();

        $categoryOptions = $tags->categoryOptions();

        return view('tags.edit', compact('tag', 'labelEn', 'labelPl', 'categoryOptions', 'suggestedBringRows'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tag $tag, TagService $tags)
    {
        $this->authorizeCreatedBy($tag);

        $validated = $request->validate([
            'tag_category_id' => ['required', 'integer', 'exists:tag_categories,id'],
            'label_en' => ['nullable', 'string', 'max:255'],
            'label_pl' => ['nullable', 'string', 'max:255'],
            'suggested_bring' => ['nullable', 'array'],
            'suggested_bring.*.label_en' => ['nullable', 'string', 'max:255'],
            'suggested_bring.*.label_pl' => ['nullable', 'string', 'max:255'],
        ]);

        $tags->updateFromValidated($tag, $validated);
        $this->syncTagBringLabels($tag, $validated);

        return redirect()->route('tags.index')
            ->with('status', __('Tag updated.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag)
    {
        $this->authorizeCreatedBy($tag);

        $tag->delete();

        return redirect()->route('tags.index')
            ->with('status', __('Tag deleted.'));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncTagBringLabels(Tag $tag, array $validated): void
    {
        $rows = collect($validated['suggested_bring'] ?? [])
            ->filter(fn ($row): bool => is_array($row) && (
                trim((string) ($row['label_en'] ?? '')) !== ''
                || trim((string) ($row['label_pl'] ?? '')) !== ''
            ))
            ->values()
            ->all();

        app(ActivitySuggestedBringService::class)->syncBringLabelsForBringable($tag, $rows);
    }
}
