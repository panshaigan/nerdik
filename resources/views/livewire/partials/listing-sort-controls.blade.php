@php
    $prefix = $sortIdPrefix ?? 'listing';
    $fields = $fields ?? ['date', 'name'];
    $dirAsc = strtolower($sort_dir ?? 'asc') === 'asc';
    $activeSort = $sort ?? 'date';
@endphp
<div class="flex flex-wrap items-center gap-x-6 gap-y-2 pl-2" data-ui="{{ $prefix }}-sort-controls">
    @foreach ($fields as $field)
        @php
            $labelKey = match ($field) {
                'host' => 'ui.browse.sort_host',
                'name' => 'ui.browse.sort_name',
                default => 'ui.browse.sort_date',
            };
            $toggleKey = match ($field) {
                'host' => 'ui.browse.sort_toggle_host',
                'name' => 'ui.browse.sort_toggle_name',
                default => 'ui.browse.sort_toggle_date',
            };
            $isActive = $activeSort === $field;
        @endphp
        <div class="flex items-center gap-1.5">
            <span class="text-sm font-medium text-base-content">{{ __($labelKey) }}</span>
            <x-button
                type="button"
                wire:click="toggleSort('{{ $field }}')"
                wire:key="{{ $prefix }}-sort-{{ $field }}"
                class="btn-ghost btn-xs h-8 min-h-8 w-8 shrink-0 rounded-lg p-0 {{ $isActive ? 'text-neutral' : 'text-base-content/35 hover:text-base-content/70' }}"
                :tooltip="__($toggleKey)"
                :aria-label="__($toggleKey)"
                aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                data-ui="{{ $prefix }}-sort-{{ $field }}"
            >
                @if ($isActive)
                    @if ($dirAsc)
                        <x-icon name="o-chevron-up" />
                    @else
                        <x-icon name="o-chevron-down" />
                    @endif
                @else
                    <x-icon name="o-arrows-up-down" />
                @endif
            </x-button>
        </div>
    @endforeach
</div>
