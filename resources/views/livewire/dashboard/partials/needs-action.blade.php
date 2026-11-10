@php
    /** @var list<\App\Services\Dashboard\DashboardActionItem> $actionItems */
@endphp

@if ($actionItems !== [])
    <section class="space-y-4" data-ui="dashboard-needs-action">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-base-content/55">
            {{ __('ui.dashboard.needs_action') }}
        </h2>
        <ul class="divide-y divide-base-300 rounded-2xl border border-base-300 bg-base-100" role="list">
            @foreach ($actionItems as $item)
                <li data-ui="dashboard-action-{{ $item->kind }}">
                    <a
                        href="{{ $item->url }}"
                        wire:navigate
                        class="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-base-200/50"
                    >
                        <x-icon :name="$item->icon" class="mt-0.5 h-5 w-5 shrink-0 text-base-content/60" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-base-content">{{ $item->title }}</span>
                            @if (filled($item->meta))
                                <span class="mt-0.5 block text-xs text-base-content/60">{{ $item->meta }}</span>
                            @endif
                        </span>
                        <x-icon name="o-chevron-right" class="mt-0.5 h-4 w-4 shrink-0 text-base-content/40" />
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
