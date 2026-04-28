<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('status'))
            <div role="alert" class="alert alert-success text-sm">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="alert alert-error text-sm">{{ $errors->first() }}</div>
        @endif

        @php
            $title = $event->name;
        @endphp

        <div id="ui-event-show-hero" class="ui-event-show-hero overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow" data-ui="event-show-hero">
            <div class="relative rounded min-h-[140px] bg-gradient-to-br from-primary/20 via-base-200/50 to-base-100 sm:min-h-[180px] p-6 sm:p-8">
                <x-header
                    title="{{ $title }}"
                    class=""
                    separator
                    use-h1
                >
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <span>{{ $title }}</span>
                        </div>
                    </x-slot:title>
                    <x-slot:subtitle>
                        {{__('Placeholder')}}
                    </x-slot:subtitle>
                    <x-slot:actions>
                        @if ($event->creator)
                            <x-user-badge
                                :user="$event->creator"
                                size="md"
                                name-class="truncate text-end font-semibold"
                                data-ui="activity-show-host"
                                title="Creator"
                            />
                        @endif
                    </x-slot:actions>
                </x-header>
            </div>
            <x-ui.tabs-with-toolbar
                wire:model.live="tab"
                label-div-class="flex gap-5 overflow-x-auto px-3 pt-2"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="w-full"
                toolbar-wrapper-class="flex shrink-0 items-center gap-1 px-2 pb-2 pt-2 sm:px-3"
                data-ui="event-show-tabs"
            >
                <x-slot:toolbar>
                    @auth
                        @if ($canManageEvent)
                            <div class="flex shrink-0 items-center gap-1" data-ui="event-show-tabs-toolbar">
                                @if ($canManageEvent)
                                    <x-button
                                        id="ui-event-show-create-slots"
                                        type="button"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary ui-action ui-action-create-slots"
                                        onclick="document.getElementById('event-slots-create-modal')?.showModal()"
                                        :tooltip="__('ui.slots.create_slots')"
                                        :aria-label="__('ui.slots.create_slots')"
                                        data-ui="event-show-create-slots"
                                        icon="o-plus"
                                    />
                                @endif
                                <x-button
                                    :link="route('events.edit', $event)"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                    :tooltip="__('Edit')"
                                    :aria-label="__('Edit').': '.$event->name"
                                    data-ui="event-show-edit-open"
                                    icon="o-pencil"
                                />
                                @if (auth()->user()?->canCreateEvents())
                                    <x-button
                                        :link="route('events.create', ['duplicate' => $event->slug])"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                        :tooltip="__('ui.events.duplicate_action')"
                                        :aria-label="__('ui.events.duplicate_action').': '.$event->name"
                                        data-ui="event-show-duplicate-open"
                                        icon="o-square-2-stack"
                                    />
                                @endif
                                <x-button
                                    type="button"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                    wire:click="deleteEvent"
                                    wire:confirm="{{ __('Are you sure you want to delete this event?') }}"
                                    :tooltip="__('Delete')"
                                    :aria-label="__('Delete').': '.$event->name"
                                    data-ui="event-show-delete"
                                    icon="o-trash"
                                />
                            </div>
                        @endif
                    @endauth
                </x-slot:toolbar>

                <x-tab name="description" :label="__('ui.events.show_about')" class="!p-0" data-ui="event-show-tab-description" icon="o-document-text">
                    @include('livewire.events.partials.show-description-tab')
                </x-tab>

                <x-tab name="plan" :label="__('ui.events.show_plan')" class="!p-0" data-ui="event-show-tab-plan" icon="o-calendar-days">
                    @include('livewire.events.partials.show-plan-tab')
                </x-tab>

                @if ($canManageEvent && $pendingProposals->isNotEmpty())
                    <x-tab name="proposals" :label="__('ui.events.show_proposals')" class="!p-0" data-ui="event-show-tab-proposals" icon="o-clipboard-document-list">
                        @include('livewire.events.partials.show-proposals-tab')
                    </x-tab>
                @endif

            </x-ui.tabs-with-toolbar>
        </div>

        @include('slots.partials.create-modal-shell', [
            'event' => $event,
            'slotMassVenues' => $slotMassVenues,
            'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
            'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
        ])
        @include('slots.partials.edit-modal-shell')
    </div>
</div>
