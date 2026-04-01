@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-base-content']) }}>
    {{ $value ?? $slot }}
</label>
