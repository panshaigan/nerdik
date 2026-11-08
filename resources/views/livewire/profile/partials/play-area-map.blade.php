<div
    data-play-area-map
    class="space-y-3 overflow-visible"
    wire:ignore
>
    <script type="application/json" data-pam-config>@json($playAreaMapConfig)</script>
    <div class="relative z-[500] max-w-xl overflow-visible">
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
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <label class="form-control w-full min-w-0 sm:max-w-xs">
            <span class="label-text font-medium">{{ __('ui.profile.availability.play_area_radius_km') }}</span>
            <input
                type="range"
                data-pam-radius
                class="range range-primary range-sm"
                min="1"
                max="300"
                step="1"
            />
        </label>
        <p class="text-sm text-base-content/80" data-pam-label></p>
    </div>
    <div
        data-pam-map
        class="z-0 w-full overflow-hidden rounded-md border border-base-300 bg-base-200/30"
        style="min-height: 280px; height: min(420px, 50vh);"
    ></div>
</div>
