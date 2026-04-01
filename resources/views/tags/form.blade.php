@csrf

<div class="space-y-4">
    <div>
        <fieldset class="fieldset py-0">
            <legend class="fieldset-legend mb-0.5">{{ __('Category') }}</legend>
            <select id="category" name="category" class="select select-bordered w-full" required>
                @foreach (['game','publisher','world','convention','engine','trigger','block','misc'] as $cat)
                    <option value="{{ $cat }}"
                        @selected(old('category', $tag->category ?? '') === $cat)>
                        {{ ucfirst($cat) }}
                    </option>
                @endforeach
            </select>
        </fieldset>
        <x-field-error :messages="$errors->get('category')" class="mt-2" />
    </div>

    <div>
        <x-input
            label="{{ __('Slug') }}"
            name="slug"
            type="text"
            value="{{ old('slug', $tag->slug ?? '') }}"
            error-field="slug"
            required
        />
    </div>

    <div>
        <x-input
            label="{{ __('Label (EN)') }}"
            name="label_en"
            type="text"
            value="{{ old('label_en', $labelEn ?? '') }}"
            error-field="label_en"
        />
    </div>

    <div>
        <x-input
            label="{{ __('Label (PL)') }}"
            name="label_pl"
            type="text"
            value="{{ old('label_pl', $labelPl ?? '') }}"
            error-field="label_pl"
        />
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('tags.index') }}" class="btn btn-outline">
        {{ __('Cancel') }}
    </a>

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('Save') }}</x-button>
</div>
