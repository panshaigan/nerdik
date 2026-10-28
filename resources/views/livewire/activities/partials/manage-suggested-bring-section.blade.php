@php
    $inheritedRows = $this->inheritedSuggestedBringRows();
    $gameCategoryId = \App\Models\TagCategory::idByKey(\App\Models\TagCategory::KEY_GAME);
@endphp

<div
    class="mt-6 space-y-5 rounded-xl border border-base-300/70 bg-base-200/35 p-4 sm:p-5"
    data-ui="activity-form-suggested-bring"
>
    <div class="flex gap-3 sm:gap-4">
        <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
            aria-hidden="true"
        >
            <x-icon name="o-briefcase" class="h-5 w-5" />
        </div>
        <div class="min-w-0 flex-1 space-y-1.5">
            <h3 class="text-base font-semibold text-base-content">{{ __('ui.activities.suggested_bring.form_heading') }}</h3>
            <p class="text-xs leading-relaxed text-base-content/65">
                <span class="font-medium text-base-content/75">{{ __('ui.activities.suggested_bring.optional_label') }}</span>
                {{ __('ui.activities.suggested_bring.form_not_a_gate') }}
            </p>
        </div>
    </div>

    @if ($inheritedRows !== [])
        <div class="space-y-2 border-t border-base-300/50 pt-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/55">
                {{ __('ui.activities.suggested_bring.inherited_heading') }}
            </p>
            <ul class="space-y-2">
                @foreach ($inheritedRows as $row)
                    <li
                        class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-base-300/60 bg-base-100/50 px-3 py-2 text-sm"
                        wire:key="inherited-bring-{{ $row['id'] }}"
                    >
                        <span class="font-medium text-base-content">{{ $row['label'] }}</span>
                        <label class="flex cursor-pointer items-center gap-2 text-xs text-base-content/70">
                            <input
                                type="checkbox"
                                class="checkbox checkbox-sm checkbox-primary"
                                wire:model.live="provided_bring_item_ids"
                                value="{{ $row['id'] }}"
                                data-ui="activity-form-suggested-bring-provided"
                            />
                            <span>{{ __('ui.activities.suggested_bring.host_provides_checkbox') }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-3 border-t border-base-300/50 pt-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/55">
            {{ __('ui.activities.suggested_bring.extras_heading') }}
        </p>
        @foreach ($suggested_bring_extras as $index => $extra)
            <div
                class="flex flex-wrap items-end gap-2 rounded-lg border border-base-300/60 bg-base-100/40 p-3"
                wire:key="suggested-bring-extra-{{ $index }}"
            >
                <div class="min-w-[12rem] flex-1">
                    <x-input
                        wire:model="suggested_bring_extras.{{ $index }}.label"
                        :label="__('ui.activities.suggested_bring.extra_item_label')"
                        type="text"
                        autocomplete="off"
                        error-field="suggested_bring_extras.{{ $index }}.label"
                    />
                </div>
                <label class="flex cursor-pointer items-center gap-2 pb-2 text-sm">
                    <input
                        type="checkbox"
                        class="checkbox checkbox-sm checkbox-primary"
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
                <div class="space-y-2 rounded-lg border border-primary/20 bg-primary/5 p-3" wire:key="new-game-bring-{{ $newTagIndex }}">
                    <p class="text-xs font-medium text-base-content/80">
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
