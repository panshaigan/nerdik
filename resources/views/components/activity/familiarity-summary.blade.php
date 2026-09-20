@props([
    'familiarity' => null,
])

@php
    $lines = app(\App\Services\ActivityFamiliarityService::class)->formatSnapshotForDisplay(
        is_array($familiarity) ? $familiarity : null
    );
@endphp

@if ($lines !== [])
    <div {{ $attributes->class(['mt-1 min-w-0 space-y-0.5']) }} data-ui="familiarity-summary">
        @foreach ($lines as $line)
            @if (($line['level_label'] ?? null) !== null)
                <p class="min-w-0 break-words text-xs leading-snug text-base-content/60">
                    <span class="font-semibold text-base-content/75">{{ $line['label'] }}</span>: {{ $line['level_label'] }}
                </p>
            @endif
        @endforeach
    </div>
@endif
