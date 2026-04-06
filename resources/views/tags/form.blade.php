@csrf

<div class="space-y-4">
    <div>
        <x-form-select id="tag_category_id" name="tag_category_id" :label="__('Category')" error-field="tag_category_id" required>
            @foreach (($categoryOptions ?? []) as $opt)
                <option value="{{ $opt['id'] }}"
                    @selected((string) old('tag_category_id', $tag->tag_category_id ?? '') === (string) $opt['id'])>
                    {{ $opt['name'] }}
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
