@php
    $selected = collect(old('tag_ids', $selectedIds ?? []))->map(fn ($id) => (string) $id)->toArray();
@endphp
@if ($tags->isEmpty())
    <p class="text-sm text-gray-500">{{ __('No tags in the system yet. Admins can add tags in the admin panel.') }}</p>
@else
    @foreach ($tags->groupBy('category') as $category => $categoryTags)
        <div class="mb-4">
            <p class="text-sm font-medium text-gray-700 mb-2">{{ ucfirst($category) }}</p>
            <div class="flex flex-wrap gap-x-4 gap-y-2">
                @foreach ($categoryTags as $tag)
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}"
                            @checked(in_array((string) $tag->id, $selected, true))>
                        <span class="text-sm text-gray-800">{{ $tag->translations->firstWhere('locale', app()->getLocale())?->label ?? $tag->slug }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
@endif
