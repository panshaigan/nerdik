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

    @php
        $bringLabels = old('suggested_bring_labels', $suggestedBringLabels ?? []);
        if (! is_array($bringLabels)) {
            $bringLabels = [];
        }
        if ($bringLabels === []) {
            $bringLabels = [''];
        }
    @endphp
    <div>
        <p class="mb-2 text-sm font-medium">{{ __('Suggested to bring (optional)') }}</p>
        <p class="mb-2 text-xs text-base-content/60">{{ __('One line per item; shown as optional suggestions on activities.') }}</p>
        @foreach ($bringLabels as $index => $bringLabel)
            <x-input
                class="mb-2"
                name="suggested_bring_labels[]"
                type="text"
                value="{{ is_string($bringLabel) ? $bringLabel : '' }}"
                error-field="suggested_bring_labels.{{ $index }}"
            />
        @endforeach
        <x-input
            name="suggested_bring_labels[]"
            type="text"
            value=""
            placeholder="{{ __('Add another…') }}"
        />
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <x-button :link="route('tags.index')" class="btn-outline">{{ __('Cancel') }}</x-button>

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('Save') }}</x-button>
</div>
