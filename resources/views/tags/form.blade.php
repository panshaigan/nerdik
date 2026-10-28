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
        $bringRows = old('suggested_bring', $suggestedBringRows ?? []);
        if (! is_array($bringRows)) {
            $bringRows = [];
        }
        if ($bringRows === []) {
            $bringRows = [['label_en' => '', 'label_pl' => '']];
        }
    @endphp
    <div>
        <p class="mb-1 text-sm font-medium">{{ __('ui.tags.suggested_bring.heading') }}</p>
        <p class="mb-3 text-xs text-base-content/60">{{ __('ui.tags.suggested_bring.help') }}</p>
        @foreach ($bringRows as $index => $row)
            <div class="mb-3 grid gap-2 sm:grid-cols-2">
                <x-input
                    name="suggested_bring[{{ $index }}][label_en]"
                    :label="__('ui.tags.suggested_bring.label_en')"
                    type="text"
                    value="{{ is_array($row) ? ($row['label_en'] ?? '') : '' }}"
                    error-field="suggested_bring.{{ $index }}.label_en"
                />
                <x-input
                    name="suggested_bring[{{ $index }}][label_pl]"
                    :label="__('ui.tags.suggested_bring.label_pl')"
                    type="text"
                    value="{{ is_array($row) ? ($row['label_pl'] ?? '') : '' }}"
                    error-field="suggested_bring.{{ $index }}.label_pl"
                />
            </div>
        @endforeach
        <div class="grid gap-2 sm:grid-cols-2">
            <x-input
                name="suggested_bring[{{ count($bringRows) }}][label_en]"
                :label="__('ui.tags.suggested_bring.label_en')"
                type="text"
                value=""
                :placeholder="__('ui.tags.suggested_bring.add_row')"
            />
            <x-input
                name="suggested_bring[{{ count($bringRows) }}][label_pl]"
                :label="__('ui.tags.suggested_bring.label_pl')"
                type="text"
                value=""
            />
        </div>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <x-button :link="route('tags.index')" class="btn-outline">{{ __('Cancel') }}</x-button>

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('Save') }}</x-button>
</div>
