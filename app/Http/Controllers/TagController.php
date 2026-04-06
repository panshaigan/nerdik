<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Traits\AuthorizesOwnership;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
    public function create()
    {
        return view('tags.create', [
            'tag' => new Tag,
            'labelEn' => '',
            'labelPl' => '',
            'categoryOptions' => $this->categoryOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', Rule::exists('tag_categories', 'key')],
            'label_en' => ['nullable', 'string', 'max:255'],
            'label_pl' => ['nullable', 'string', 'max:255'],
        ]);

        $labelsByLocale = $this->normalizedLabelsByLocale($validated);

        // Ensure a translation (and thus locale slug) exists in current app locale.
        $currentLocale = app()->getLocale();
        if (($labelsByLocale[$currentLocale] ?? '') === '') {
            $fallback = collect($labelsByLocale)->first(fn ($label) => $label !== '');
            if ($fallback !== null) {
                $labelsByLocale[$currentLocale] = $fallback;
            }
        }

        if (! collect($labelsByLocale)->contains(fn ($label) => $label !== '')) {
            return back()
                ->withInput()
                ->withErrors(['label_'.$currentLocale => __('At least one label is required.')]);
        }

        $categoryId = TagCategory::query()
            ->where('key', $validated['category'])
            ->value('id');

        $tag = Tag::create([
            'tag_category_id' => $categoryId,
        ]);

        foreach (['en', 'pl'] as $locale) {
            if (($labelsByLocale[$locale] ?? '') !== '') {
                TagTranslation::create([
                    'tag_id' => $tag->id,
                    'locale' => $locale,
                    'label' => $labelsByLocale[$locale],
                ]);
            }
        }

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
    public function edit(Tag $tag)
    {
        $this->authorizeCreatedBy($tag);

        $tag->loadMissing('translations');
        $labelEn = $tag->translations->firstWhere('locale', 'en')?->label ?? '';
        $labelPl = $tag->translations->firstWhere('locale', 'pl')?->label ?? '';

        $categoryOptions = $this->categoryOptions();

        return view('tags.edit', compact('tag', 'labelEn', 'labelPl', 'categoryOptions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tag $tag)
    {
        $this->authorizeCreatedBy($tag);

        $validated = $request->validate([
            'category' => ['required', Rule::exists('tag_categories', 'key')],
            'label_en' => ['nullable', 'string', 'max:255'],
            'label_pl' => ['nullable', 'string', 'max:255'],
        ]);

        $categoryId = TagCategory::query()
            ->where('key', $validated['category'])
            ->value('id');

        $tag->update([
            'tag_category_id' => $categoryId,
        ]);

        $labelsByLocale = $this->normalizedLabelsByLocale($validated);
        $currentLocale = app()->getLocale();
        if (($labelsByLocale[$currentLocale] ?? '') === '') {
            $fallback = collect($labelsByLocale)->first(fn ($label) => $label !== '');
            if ($fallback !== null) {
                $labelsByLocale[$currentLocale] = $fallback;
            }
        }

        if (! collect($labelsByLocale)->contains(fn ($label) => $label !== '')) {
            return back()
                ->withInput()
                ->withErrors(['label_'.$currentLocale => __('At least one label is required.')]);
        }

        foreach (['en', 'pl'] as $locale) {
            $value = $labelsByLocale[$locale] ?? null;

            $translation = $tag->translations()->firstOrNew(['locale' => $locale]);

            if ($value) {
                $translation->label = $value;
                $translation->save();
            } elseif ($translation->exists) {
                $translation->delete();
            }
        }

        return redirect()->route('tags.index')
            ->with('status', __('Tag updated.'));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{en: string, pl: string}
     */
    private function normalizedLabelsByLocale(array $validated): array
    {
        return [
            'en' => trim((string) ($validated['label_en'] ?? '')),
            'pl' => trim((string) ($validated['label_pl'] ?? '')),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        $locale = app()->getLocale();

        return TagCategory::query()
            ->with('translations')
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (TagCategory $category) => [$category->key => $category->name($locale)])
            ->all();
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
}
