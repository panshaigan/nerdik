<?php

namespace App\Http\Controllers;

use App\Models\Tag;
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
        ]);

        $tags->createFromValidated($validated);

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

        $categoryOptions = $tags->categoryOptions();

        return view('tags.edit', compact('tag', 'labelEn', 'labelPl', 'categoryOptions'));
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
        ]);

        $tags->updateFromValidated($tag, $validated);

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
