@csrf

<div class="space-y-4">
    <div>
        <x-input-label for="category" :value="__('Category')" />
        <select id="category" name="category" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            @foreach (['game','publisher','world','convention','engine','trigger','block','misc'] as $cat)
                <option value="{{ $cat }}"
                    @selected(old('category', $tag->category ?? '') === $cat)>
                    {{ ucfirst($cat) }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="slug" :value="__('Slug')" />
        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full"
                      value="{{ old('slug', $tag->slug ?? '') }}" required />
        <x-input-error :messages="$errors->get('slug')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="label_en" :value="__('Label (EN)')" />
        <x-text-input id="label_en" name="label_en" type="text" class="mt-1 block w-full"
                      value="{{ old('label_en', $labelEn ?? '') }}" />
        <x-input-error :messages="$errors->get('label_en')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="label_pl" :value="__('Label (PL)')" />
        <x-text-input id="label_pl" name="label_pl" type="text" class="mt-1 block w-full"
                      value="{{ old('label_pl', $labelPl ?? '') }}" />
        <x-input-error :messages="$errors->get('label_pl')" class="mt-2" />
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('tags.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
        {{ __('Cancel') }}
    </a>

    <x-primary-button>
        {{ $submitLabel ?? __('Save') }}
    </x-primary-button>
</div>

