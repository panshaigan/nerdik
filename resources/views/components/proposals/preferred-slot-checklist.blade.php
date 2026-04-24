@props([
    'slots',
    'wireModel',
    'errorField' => null,
    'readonly' => false,
])

@php
    $field = $errorField ?? $wireModel;
@endphp

<div>
    <p class="fieldset-legend font-medium text-base-content">{{ __('ui.proposals.preferred_slots_optional') }}</p>
    <p class="mb-2 text-sm text-base-content/60">{{ __('ui.proposals.preferred_slots_help') }}</p>
    <div class="max-h-64 space-y-2 overflow-y-auto pr-1">
        @foreach ($slots as $slot)
            @php
                $slotLabel = '';
                if ($slot->starts_at) {
                    $slotLabel .= format_datetime_in_user_tz($slot->starts_at).' ';
                }
                $taken = '';
                if ($slot->activity_id) {
                    $taken = ' ('.__('ui.proposals.taken').')';
                }
            @endphp
            <label @class([
                'flex items-start gap-3 rounded-lg border border-base-300/80 bg-base-200/30 px-3 py-2',
                'cursor-pointer' => ! $readonly,
                'cursor-not-allowed opacity-70' => $readonly,
            ])>
                <input
                    type="checkbox"
                    class="checkbox checkbox-sm mt-0.5"
                    wire:model="{{ $wireModel }}"
                    value="{{ $slot->id }}"
                    @disabled((bool) $slot->activity_id || $readonly)
                >
                <span class=""><x-icon name="o-calendar" />{{ $slotLabel }}</span> <span class=""><x-icon name="o-home" />{{$slot->name}}</span> {{ $taken }}
            </label>
        @endforeach
    </div>
    <x-field-error :messages="$errors->get($field)" class="mt-2" />
    <x-field-error :messages="$errors->get($field.'.*')" class="mt-2" />
</div>
