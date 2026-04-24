@props(['model'])

@php
    $routeName = \Illuminate\Support\Str::plural(\Illuminate\Support\Str::kebab(class_basename($model))) . '.show';
    $href = route($routeName, $model);
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'link link-primary']) }}>
    {{ $model->name }}
</a>
