@props([
    'hasInterest' => false,
    'count' => 0,
    'dataUiPrefix',
])

@php
    $countDataUi = $dataUiPrefix.'-interest-count';
    $addDataUi = $dataUiPrefix.'-interest-add';
    $removeDataUi = $dataUiPrefix.'-interest-remove';
    $buttonClass = 'btn-ghost btn-square h-12 w-12 min-h-12 p-0 [&_svg]:h-8 [&_svg]:w-8';
@endphp

@auth
    @if ($hasInterest)
        <x-ui.icon-count-badge :count="$count" data-ui="{{ $countDataUi }}">
            <x-button
                type="button"
                wire:click="removeInterest"
                @class([$buttonClass, 'text-warning ui-action ui-action-interest-remove'])
                :tooltip="__('ui.interests.remove_from_interests')"
                :aria-label="__('ui.interests.remove_from_interests')"
                data-ui="{{ $removeDataUi }}"
                icon="s-star"
            />
        </x-ui.icon-count-badge>
    @else
        <x-ui.icon-count-badge :count="$count" data-ui="{{ $countDataUi }}">
            <x-button
                type="button"
                wire:click="addInterest"
                @class([$buttonClass, 'text-base-content/80 hover:text-warning ui-action ui-action-interest-add'])
                :tooltip="__('ui.interests.add_to_interests')"
                :aria-label="__('ui.interests.add_to_interests')"
                data-ui="{{ $addDataUi }}"
                icon="o-star"
            />
        </x-ui.icon-count-badge>
    @endif
@else
    <x-ui.icon-count-badge :count="$count" data-ui="{{ $countDataUi }}">
        <span
            @class(['btn btn-ghost btn-square pointer-events-none text-base-content/80', 'h-12 w-12 min-h-12'])
            aria-hidden="true"
        >
            <x-icon name="o-star" class="h-8 w-8" />
        </span>
    </x-ui.icon-count-badge>
@endauth
