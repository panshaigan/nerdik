@php
    $title = __('ui.places.edit').': '.$this->name;
@endphp
<div>
    <x-page-header :title="$title" :user="$creator" :back-url="$backUrl" class="mb-4" />

    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="mb-10" />

    <div class="ui-content-card relative min-w-0 rounded-2xl mb-4 md:mb-6">
        <x-form wire:submit.prevent="save" novalidate class="" data-place-form>
            <div id="ui-place-form-fields" class="ui-form ui-form-place min-w-0 space-y-6 px-4 py-4 sm:px-6 sm:py-6" data-ui="place-form-fields">
                <x-input
                    wire:model="name"
                    label="{{ __('ui.common.name') }}"
                    placeholder="{{ __('ui.common.name') }}"
                    type="text"
                    error-field="name"
                    required
                    inline
                />

                <div class="space-y-3" data-ui="place-form-location">
                    <div>
                        <p class="text-sm font-semibold text-base-content">{{ __('ui.places.location_section') }}</p>
                        <p class="mt-1 text-xs text-base-content/70">{{ __('ui.places.location_hint') }}</p>
                    </div>

                    <div
                        data-place-location-map
                        class="space-y-3"
                        wire:ignore
                    >
                        <script type="application/json" data-plm-config>@json($this->mapConfig)</script>
                        <div class="relative z-[500] max-w-xl">
                            <x-input
                                type="search"
                                data-plm-search
                                autocomplete="off"
                                :label="__('ui.places.location_section')"
                                :placeholder="__('ui.places.location_search_placeholder')"
                                class="w-full"
                                :omit-error="true"
                                inline
                            />
                            <div
                                data-plm-results
                                class="absolute left-0 right-0 top-full z-[1001] mt-1 hidden max-h-60 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                            ></div>
                        </div>
                        <div
                            data-plm-map
                            class="z-0 w-full overflow-hidden rounded-md border border-base-300 bg-base-200/30"
                            style="min-height: 280px; height: min(420px, 50vh);"
                        ></div>
                    </div>

                    <p class="text-sm text-base-content/80" data-ui="place-form-address-summary">
                        <span class="font-medium">{{ __('ui.places.address_label') }}:</span>
                        <span wire:key="place-address-{{ md5((string) $address) }}">
                            {{ filled($address) ? $address : __('ui.places.no_address') }}
                        </span>
                    </p>
                    <x-field-error :messages="$errors->get('latitude')" class="mt-1" />
                    <x-field-error :messages="$errors->get('longitude')" class="mt-1" />
                </div>

                @if ($isVenue)
                    <div class="space-y-3" data-ui="place-form-rooms">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-base-content">{{ __('ui.places.rooms_section') }}</p>
                                <p class="mt-1 text-xs text-base-content/70">{{ __('ui.places.rooms_hint') }}</p>
                            </div>
                            <x-button
                                type="button"
                                class="btn-outline btn-sm"
                                icon="o-plus"
                                wire:click="addRoom"
                                :tooltip="__('ui.places.add_room')"
                                :aria-label="__('ui.places.add_room')"
                                data-ui="place-form-add-room"
                            >
                                {{ __('ui.places.add_room') }}
                            </x-button>
                        </div>

                        <x-field-error :messages="$errors->get('rooms')" class="mt-1" />

                        @forelse ($rooms as $index => $room)
                            <div
                                wire:key="place-room-{{ $room['key'] }}"
                                class="flex items-start gap-2"
                                data-ui="place-form-room-row"
                            >
                                <div class="min-w-0 flex-1">
                                    <x-input
                                        wire:model="rooms.{{ $index }}.name"
                                        :label="__('ui.places.room_name')"
                                        :placeholder="__('ui.places.room_placeholder')"
                                        type="text"
                                        error-field="rooms.{{ $index }}.name"
                                        inline
                                    />
                                </div>
                                <x-button
                                    type="button"
                                    class="btn-ghost btn-sm btn-square mt-8 text-error"
                                    icon="o-trash"
                                    wire:click="removeRoom('{{ $room['key'] }}')"
                                    :tooltip="__('ui.places.remove_room')"
                                    :aria-label="__('ui.places.remove_room')"
                                    data-ui="place-form-remove-room"
                                />
                            </div>
                        @empty
                            <p class="text-sm text-base-content/60">{{ __('ui.places.empty_rooms') }}</p>
                        @endforelse
                    </div>
                @endif

                <div class="space-y-2" data-ui="place-form-entity-links">
                    <p class="text-sm font-semibold text-base-content">{{ __('ui.entity_links.section') }}</p>
                    <livewire:entity-links.manage-entity-links
                        :linkable="$place"
                        :show-list="true"
                        :show-add-button="true"
                        appearance="default"
                        data-ui="place-form-entity-links"
                        :key="'place-entity-links-'.$editingPlaceId"
                    />
                </div>
            </div>

            <x-slot:actions class="px-4 pb-4 sm:px-6 sm:pb-6">
                <x-button id="ui-place-cancel" :link="$cancelUrl" class="btn-outline ui-action ui-action-cancel" data-ui="place-cancel">
                    {{ __('ui.common.cancel') }}
                </x-button>
                <x-button id="ui-place-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="place-submit" wire:loading.attr="disabled" wire:target="save" spinner="save">
                    <span wire:loading.remove wire:target="save">{{ $submitLabel }}</span>
                    <span wire:loading wire:target="save">{{ __('ui.common.saving') }}</span>
                </x-button>
            </x-slot:actions>
        </x-form>
    </div>
</div>
