<section class="mt-12" data-ui="groups-find">
    <header class="mb-4">
        <h2 class="text-lg font-semibold text-base-content">{{ __('ui.groups.find_title') }}</h2>
        <p class="mt-1 text-sm text-base-content/70">{{ __('ui.groups.find_hint') }}</p>
    </header>

    <div class="mb-6 space-y-4 rounded-lg border border-base-300 bg-base-100 p-4" data-ui="groups-find-filters">
        <x-input wire:model.live.debounce.300ms="nameQuery" :label="__('ui.groups.find_name')" />

        <div class="overflow-visible" data-ui="groups-find-tags">
            <x-activity.category-tags-picker :config="$this->tagPickerConfig" />
        </div>

        @if ($viewerHasPlayArea)
            <label class="flex cursor-pointer items-center gap-3">
                <input type="checkbox" class="checkbox checkbox-primary checkbox-sm" wire:model.live="nearMyPlayArea" />
                <span class="text-sm text-base-content">{{ __('ui.groups.find_near_play_area') }}</span>
            </label>
        @endif
    </div>

    <ul class="space-y-3" role="list" data-ui="groups-find-list">
        @forelse ($groups as $group)
            <li wire:key="find-group-{{ $group->id }}" class="rounded-lg border border-base-300 bg-base-100 p-4 shadow-sm">
                <a
                    href="{{ route('groups.show', $group) }}"
                    wire:navigate
                    class="block font-medium text-base-content hover:text-primary"
                    data-ui="groups-find-item"
                >
                    {{ $group->name }}
                </a>
                @if (filled($group->description))
                    <p class="mt-2 text-sm text-base-content/70">{{ $group->description }}</p>
                @endif
                <p class="mt-2 text-xs text-base-content/60">
                    {{ trans_choice('ui.groups.member_count', $group->users_count, ['count' => $group->users_count]) }}
                </p>
                @if ($group->tags->isNotEmpty())
                    <ul class="mt-2 flex flex-wrap gap-2" role="list">
                        @foreach ($group->tags as $tag)
                            <li class="badge badge-outline badge-sm">{{ $tag->displayLabel($locale) }}</li>
                        @endforeach
                    </ul>
                @endif
                @if ($group->hasLocation())
                    <p class="mt-2 text-xs text-base-content/60">
                        {{ $group->location_label ?: __('ui.groups.location_untitled') }}
                        · {{ __('ui.groups.location_radius_summary', ['km' => (int) $group->radius_km]) }}
                    </p>
                @endif
            </li>
        @empty
            <li class="rounded-lg border border-dashed border-base-300 bg-base-100/50 px-4 py-8 text-center text-sm text-base-content/70">
                {{ __('ui.groups.find_empty') }}
            </li>
        @endforelse
    </ul>

    @if ($groups instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $groups->hasPages())
        <div class="mt-6">
            {{ $groups->links() }}
        </div>
    @endif
</section>
