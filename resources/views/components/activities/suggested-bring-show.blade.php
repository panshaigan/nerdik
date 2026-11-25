@props([
    'rows' => [],
])

@php
    $participantRows = collect($rows)
        ->reject(fn (array $row): bool => (bool) ($row['host_provides'] ?? false))
        ->values()
        ->all();
    $hostRows = collect($rows)
        ->filter(fn (array $row): bool => (bool) ($row['host_provides'] ?? false))
        ->values()
        ->all();
@endphp

@if ($rows !== [])
    <section
        {{ $attributes->class(['rounded-xl border border-base-300/70 bg-base-200/35 p-4 sm:p-5']) }}
        data-ui="activity-show-suggested-bring"
    >
        <div class="flex gap-3 sm:gap-4">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                aria-hidden="true"
            >
                <x-icon name="o-briefcase" class="h-5 w-5" />
            </div>

            <div class="min-w-0 flex-1 space-y-3">
                <div class="space-y-1.5">
                    <h3 class="text-base font-semibold leading-snug text-base-content">
                        {{ __('ui.activities.suggested_bring.heading') }}
                    </h3>
                    <p class="text-xs leading-relaxed text-base-content/65">
                        <span class="font-medium text-base-content/75">{{ __('ui.activities.suggested_bring.optional_label') }}</span>
                        {{ __('ui.activities.suggested_bring.not_a_gate') }}
                    </p>
                </div>

                @if ($participantRows !== [])
                    <ul class="flex flex-wrap gap-2" role="list">
                        @foreach ($participantRows as $row)
                            <li
                                class="inline-flex max-w-full items-center gap-2 rounded-full border border-base-300/80 bg-base-100/60 px-3 py-1.5 text-sm text-base-content shadow-sm"
                                data-ui="activity-show-suggested-bring-item"
                            >
                                <span class="truncate">{{ $row['label'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($hostRows !== [])
                    <div
                        class="space-y-2 rounded-lg border border-success/25 bg-success/5 p-3"
                        data-ui="activity-show-suggested-bring-host-provides"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-success/90">
                            {{ __('ui.activities.suggested_bring.host_provides') }}
                        </p>
                        <ul class="flex flex-wrap gap-2" role="list">
                            @foreach ($hostRows as $row)
                                <li
                                    class="inline-flex max-w-full items-center gap-2 rounded-full border border-base-300/80 bg-base-100/70 px-3 py-1.5 text-sm text-base-content shadow-sm"
                                    data-ui="activity-show-suggested-bring-item"
                                >
                                    <span class="truncate">{{ $row['label'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
