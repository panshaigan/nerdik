@props([
    'familiarity' => null,
])

@php
    $summary = app(\App\Services\ActivityFamiliarityService::class)->formatSnapshotSummary(
        is_array($familiarity) ? $familiarity : null
    );
@endphp

@if ($summary !== '')
    <p {{ $attributes->class(['mt-1 text-xs leading-snug text-base-content/60']) }} data-ui="familiarity-summary">
        {{ $summary }}
    </p>
@endif
