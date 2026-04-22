@props([
    'slots',
    'wireModel',
    'errorField' => null,
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
                $slotLabel = $slot->name;
                if ($slot->starts_at) {
                    $slotLabel .= ' · '.format_in_user_tz($slot->starts_at, 'Y-m-d H:i');
                }
                if ($slot->activity_id) {
                    $slotLabel .= ' ('.__('ui.proposals.taken').')';
                }
            @endphp
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300/80 bg-base-200/30 px-3 py-2">
                <input
                    type="checkbox"
                    class="checkbox checkbox-sm mt-0.5"
                    wire:model="{{ $wireModel }}"
                    value="{{ $slot->id }}"
                    @disabled((bool) $slot->activity_id)
                >
                <span class="text-sm">{{ $slotLabel }}</span>
            </label>
        @endforeach
    </div>
    <x-field-error :messages="$errors->get($field)" class="mt-2" />
    <x-field-error :messages="$errors->get($field.'.*')" class="mt-2" />
</div>
