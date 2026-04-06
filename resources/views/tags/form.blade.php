@csrf

<div class="space-y-4">
    <div>
        <x-form-select id="category" name="category" :label="__('Category')" error-field="category" required>
            @foreach (['game','publisher','world','convention','engine','trigger','block','misc'] as $cat)
                <option value="{{ $cat }}"
                    @selected(old('category', $tag->category ?? '') === $cat)>
                    {{ ucfirst($cat) }}
                </option>
            @endforeach
        </x-form-select>
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
    <x-button :link="route('tags.index')" class="btn-outline">{{ __('Cancel') }}</x-button>

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('Save') }}</x-button>
</div>
