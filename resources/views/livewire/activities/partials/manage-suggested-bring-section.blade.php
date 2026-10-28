@php
    $inheritedRows = $this->inheritedSuggestedBringRows();
    $gameCategoryId = \App\Models\TagCategory::idByKey(\App\Models\TagCategory::KEY_GAME);
@endphp

<div class="pt-6 space-y-4" data-ui="activity-form-suggested-bring">
    <div>
        <h3 class="text-sm font-semibold text-base-content">{{ __('ui.activities.suggested_bring.form_heading') }}</h3>
        <p class="text-xs text-base-content/60 underline decoration-base-content/30 underline-offset-2">
            {{ __('ui.activities.suggested_bring.form_not_a_gate') }}
        </p>
    </div>

    @if ($inheritedRows !== [])
        <div class="space-y-2">
            <p class="text-xs font-medium text-base-content/70">{{ __('ui.activities.suggested_bring.inherited_heading') }}</p>
            <ul class="space-y-2">
                @foreach ($inheritedRows as $row)
                    <li class="flex flex-wrap items-center gap-2 text-sm">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                class="checkbox checkbox-sm"
                                wire:model.live="provided_bring_item_ids"
                                value="{{ $row['id'] }}"
                                data-ui="activity-form-suggested-bring-provided"
                            />
                            <span>{{ $row['label'] }}</span>
                        </label>
                        <span class="text-xs text-base-content/60">{{ __('ui.activities.suggested_bring.host_provides_checkbox') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-3">
        <p class="text-xs font-medium text-base-content/70">{{ __('ui.activities.suggested_bring.extras_heading') }}</p>
        @foreach ($suggested_bring_extras as $index => $extra)
            <div class="flex flex-wrap items-end gap-2" wire:key="suggested-bring-extra-{{ $index }}">
                <div class="min-w-[12rem] flex-1">
                    <x-input
                        wire:model="suggested_bring_extras.{{ $index }}.label"
                        :label="__('ui.activities.suggested_bring.extras_heading')"
                        type="text"
                        autocomplete="off"
                        error-field="suggested_bring_extras.{{ $index }}.label"
                    />
                </div>
                <label class="flex cursor-pointer items-center gap-2 pb-2 text-sm">
                    <input
                        type="checkbox"
                        class="checkbox checkbox-sm"
                        wire:model="suggested_bring_extras.{{ $index }}.host_provides"
                    />
                    <span>{{ __('ui.activities.suggested_bring.host_provides_checkbox') }}</span>
                </label>
                <x-button
                    type="button"
                    class="btn-ghost btn-sm"
                    wire:click="removeSuggestedBringExtraRow({{ $index }})"
                    :label="__('ui.activities.suggested_bring.remove_extra')"
                />
            </div>
        @endforeach
        <x-button
            type="button"
            class="btn-outline btn-sm"
            wire:click="addSuggestedBringExtraRow"
            icon="o-plus"
            :label="__('ui.activities.suggested_bring.add_extra')"
        />
    </div>

    @if ($gameCategoryId !== null)
        @foreach ($new_tags as $newTagIndex => $newTag)
            @if ((int) ($newTag['category_id'] ?? 0) === (int) $gameCategoryId)
                <div class="space-y-2 rounded-lg border border-base-300 p-3" wire:key="new-game-bring-{{ $newTagIndex }}">
                    <p class="text-xs font-medium text-base-content/70">
                        {{ __('ui.activities.suggested_bring.new_game_tag_heading', ['label' => $newTag['label'] ?? '']) }}
                    </p>
                    @php
                        $bringLines = $newTag['suggested_bring'] ?? [];
                        if (! is_array($bringLines)) {
                            $bringLines = [];
                        }
                    @endphp
                    @foreach ($bringLines as $lineIndex => $line)
                        <div class="flex gap-2" wire:key="new-game-bring-line-{{ $newTagIndex }}-{{ $lineIndex }}">
                            <x-input
                                wire:model="new_tags.{{ $newTagIndex }}.suggested_bring.{{ $lineIndex }}"
                                type="text"
                                autocomplete="off"
                                class="flex-1"
                            />
                            <x-button
                                type="button"
                                class="btn-ghost btn-sm shrink-0"
                                wire:click="removeNewTagSuggestedBringLine({{ $newTagIndex }}, {{ $lineIndex }})"
                                :label="__('ui.activities.suggested_bring.remove_extra')"
                            />
                        </div>
                    @endforeach
                    <x-button
                        type="button"
                        class="btn-outline btn-sm"
                        wire:click="addNewTagSuggestedBringLine({{ $newTagIndex }})"
                        icon="o-plus"
                        :label="__('ui.activities.suggested_bring.add_new_game_line')"
                    />
                </div>
            @endif
        @endforeach
    @endif

    <x-field-error :messages="$errors->get('provided_bring_item_ids')" class="mt-2" />
    <x-field-error :messages="$errors->get('suggested_bring_extras')" class="mt-2" />
</div>
