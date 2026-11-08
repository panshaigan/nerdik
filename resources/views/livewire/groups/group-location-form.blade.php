<section data-ui="group-location-form">
    <header class="mb-4">
        <h2 class="text-lg font-medium text-base-content">
            {{ __('ui.groups.location_title') }}
        </h2>
    </header>

    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.form_errors')" icon="o-face-frown" class="!mx-0 mb-4" />

    @if ($canManage)
        <div class="space-y-4 overflow-visible" data-ui="group-location-editor">
            @include('livewire.profile.partials.play-area-map', [
                'playAreaMapConfig' => $this->playAreaMapConfig,
            ])
        </div>

        <div class="mt-6 flex items-center justify-end gap-3" data-ui="group-location-save">
            <x-action-message class="me-3" on="group-location-updated">{{ __('ui.common.saved') }}</x-action-message>
            <x-button
                type="button"
                class="btn-primary"
                wire:click="save"
                spinner="save"
            >
                {{ __('ui.common.save') }}
            </x-button>
        </div>
    @elseif ($group->hasLocation())
        <div class="rounded-lg border border-base-300 bg-base-100 px-4 py-3 text-sm" data-ui="group-location-readonly">
            <p class="font-medium text-base-content">
                {{ $group->location_label ?: __('ui.groups.location_untitled') }}
            </p>
            <p class="mt-1 text-base-content/70">
                {{ __('ui.groups.location_radius_summary', ['km' => (int) $group->radius_km]) }}
            </p>
        </div>
    @else
        <p class="text-sm text-base-content/60" data-ui="group-location-empty">
            {{ __('ui.groups.location_empty') }}
        </p>
    @endif
</section>
