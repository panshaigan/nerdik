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
            ->orderBy('slug')
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
            'slug' => ['required', 'string', 'max:255', 'unique:tags,slug'],
            'label_en' => ['nullable', 'string', 'max:255'],
            'label_pl' => ['nullable', 'string', 'max:255'],
        ]);

        $tag = Tag::create([
            'category' => $validated['category'],
            'slug' => $validated['slug'],
        ]);

        foreach (['en' => 'label_en', 'pl' => 'label_pl'] as $locale => $field) {
            if (! empty($validated[$field])) {
                TagTranslation::create([
                    'tag_id' => $tag->id,
                    'locale' => $locale,
                    'label' => $validated[$field],
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
            'slug' => ['required', 'string', 'max:255', 'unique:tags,slug,'.$tag->id],
            'label_en' => ['nullable', 'string', 'max:255'],
            'label_pl' => ['nullable', 'string', 'max:255'],
        ]);

        $tag->update([
            'category' => $validated['category'],
            'slug' => $validated['slug'],
        ]);

        foreach (['en' => 'label_en', 'pl' => 'label_pl'] as $locale => $field) {
            $value = $validated[$field] ?? null;

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
