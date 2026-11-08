<div
    data-play-area-map
    class="space-y-3 overflow-visible"
    wire:ignore
>
    <script type="application/json" data-pam-config>@json($playAreaMapConfig)</script>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div class="relative z-[500] min-w-0 flex-1 max-w-xl overflow-visible">
            <x-input
                type="search"
                data-pam-search
                autocomplete="off"
                :label="__('ui.profile.availability.play_area_search_label')"
                :placeholder="__('ui.profile.availability.play_area_search_placeholder')"
                class="w-full"
                :omit-error="true"
                inline
            />
            <div
                data-pam-results
                class="absolute left-0 right-0 top-full z-[1001] mt-1 hidden max-h-60 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
            ></div>
        </div>
        <button
            type="button"
            data-pam-clear
            class="btn btn-ghost btn-sm text-error hidden shrink-0"
        >
            {{ __('ui.profile.availability.play_area_clear') }}
        </button>
    </div>
    <div class="flex flex-col gap-2 sm:max-w-md">
        <div class="flex items-center justify-between gap-3">
            <span class="text-sm font-medium text-base-content">{{ __('ui.profile.availability.play_area_radius_km') }}</span>
            <span class="text-sm tabular-nums text-base-content/80">
                <span data-pam-radius-value>{{ (int) ($playAreaMapConfig['radiusKm'] ?? 25) }}</span>
                {{ __('ui.profile.availability.play_area_radius_unit') }}
            </span>
        </div>
        <input
            type="range"
            data-pam-radius
            class="range range-primary range-sm w-full"
            min="{{ (int) ($playAreaMapConfig['minRadiusKm'] ?? 1) }}"
            max="{{ (int) ($playAreaMapConfig['maxRadiusKm'] ?? 300) }}"
            step="1"
            value="{{ (int) ($playAreaMapConfig['radiusKm'] ?? 25) }}"
            aria-label="{{ __('ui.profile.availability.play_area_radius_km') }}"
        />
        <p class="text-sm text-base-content/80" data-pam-label></p>
    </div>
    <div
        data-pam-map
        class="z-0 w-full overflow-hidden rounded-md border border-base-300 bg-base-200/30"
        style="min-height: 280px; height: min(420px, 50vh);"
    ></div>
</div>
