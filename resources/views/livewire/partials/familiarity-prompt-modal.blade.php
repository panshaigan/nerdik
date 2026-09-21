@php
    $familiarityLevelOptions = \App\Enums\FamiliarityLevel::radioOptions();
@endphp

<x-modal
    wire:model="familiarityModalOpen"
    box-class="ui-modal-surface ui-overlay-shell ui-overlay-sheet"
    class="backdrop-blur modal-bottom md:modal-end"
    data-ui="familiarity-prompt-modal"
    persistent
    separator
>
    <div class="space-y-4" data-ui="familiarity-prompt">
        <div>
            <h2 class="text-lg font-bold text-base-content">{{ __('ui.familiarity.title') }}</h2>
        </div>

        <div class="max-h-[60vh] space-y-5 overflow-y-auto pr-1">
            @foreach ($familiaritySubjects as $subject)
                <div wire:key="familiarity-subject-{{ $subject['key'] }}" class="space-y-2">
                    <x-radio
                        wire:model="familiarityAnswers.{{ $subject['key'] }}"
                        :label="$subject['label']"
                        :options="$familiarityLevelOptions"
                        :error-field="'familiarity_answers.'.$subject['key']"
                    />
                </div>
            @endforeach
        </div>
    </div>

    <x-slot:actions>
        <div class="flex w-full flex-wrap items-center justify-end gap-2">
            <x-button
                type="button"
                class="btn-ghost"
                wire:click="skipFamiliarityPrompt"
                spinner="skipFamiliarityPrompt"
            >
                {{ __('ui.familiarity.skip_all') }}
            </x-button>
            <x-button
                type="button"
                class="btn-primary"
                wire:click="submitFamiliarityPrompt"
                spinner="submitFamiliarityPrompt"
            >
                {{ __('ui.familiarity.continue') }}
            </x-button>
        </div>
    </x-slot:actions>
</x-modal>
