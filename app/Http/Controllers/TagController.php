<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\TagTranslation;
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
            ->orderBy('category')
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
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:50'],
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

        $tag = Tag::create([
            'category' => $validated['category'],
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

        return view('tags.edit', compact('tag', 'labelEn', 'labelPl'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tag $tag)
    {
        $this->authorizeCreatedBy($tag);

        $validated = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'label_en' => ['nullable', 'string', 'max:255'],
            'label_pl' => ['nullable', 'string', 'max:255'],
        ]);

        $tag->update([
            'category' => $validated['category'],
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
