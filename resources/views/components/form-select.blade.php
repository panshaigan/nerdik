{{--
    DaisyUI <select> with Mary-style fieldset/legend.

    Prefer Mary <x-select> with :options + wire:model for Livewire.

    Keep this component when:
    - Classic POST forms need @selected(old(...)) (Mary <x-select> does not mark selected options).
    - Options need extra attributes (e.g. data-country on <option>) or slots Mary cannot express.
--}}
@props([
    'label' => null,
    'errorField' => null,
    'omitError' => false,
])

@php
    $errorName = $errorField ?? $attributes->get('name');
@endphp

<div>
    <fieldset class="fieldset py-0">
        @if ($label)
            <legend class="fieldset-legend mb-0.5 font-medium">
                {{ $label }}
                @if ($attributes->get('required'))
                    <span class="text-error">*</span>
                @endif
            </legend>
        @endif

        <label @class([
            'select w-full',
            '!select-error' => ! $omitError && $errorName && $errors->has($errorName),
        ])>
            <select {{ $attributes->whereDoesntStartWith('class') }}>
                {{ $slot }}
            </select>
        </label>

        @if (! $omitError && $errorName && $errors->has($errorName))
            @foreach ($errors->get($errorName) as $message)
                <div class="text-error">{{ $message }}</div>
            @endforeach
        @endif
    </fieldset>
</div>
