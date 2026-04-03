{{--
    DaisyUI <select> with Mary-style fieldset/legend. Use when options need @selected (old() / dynamic data).
    Mary <x-select> does not render per-option selected state for plain Blade forms.
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
