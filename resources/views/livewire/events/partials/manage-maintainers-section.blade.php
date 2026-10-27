@php($editingEvent = $this->editingEventModel())
@if ($editingEvent && auth()->user()?->canManageEventMaintainers($editingEvent))
    <div class="mt-8 border-t border-base-300 pt-6" data-ui="event-maintainers-section">
        <h3 class="text-base font-semibold text-base-content">{{ __('ui.events.maintainers.heading') }}</h3>
        <p class="mt-1 text-sm text-base-content/70">{{ __('ui.events.maintainers.description') }}</p>

        <ul class="mt-4 space-y-2" data-ui="event-maintainers-list">
            <li class="flex items-center gap-3 rounded-lg border border-base-300 bg-base-100/50 p-3">
                <div class="avatar shrink-0">
                    <div class="h-9 w-9 overflow-hidden rounded-full">
                        <img
                            src="{{ $editingEvent->creator?->avatarUrl() }}"
                            alt="{{ $editingEvent->creator?->nickname }}"
                            class="h-full w-full object-cover"
                        />
                    </div>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ $editingEvent->creator?->nickname }}</p>
                    <p class="text-xs text-base-content/60">{{ __('ui.events.maintainers.owner_label') }}</p>
                </div>
            </li>
            @foreach ($editingEvent->maintainers as $maintainer)
                <li
                    wire:key="event-maintainer-{{ $maintainer->id }}"
                    class="flex items-center gap-3 rounded-lg border border-base-300 bg-base-100 p-3"
                >
                    <div class="avatar shrink-0">
                        <div class="h-9 w-9 overflow-hidden rounded-full">
                            <img
                                src="{{ $maintainer->avatarUrl() }}"
                                alt="{{ $maintainer->nickname }}"
                                class="h-full w-full object-cover"
                            />
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ $maintainer->nickname }}</p>
                        <p class="truncate text-sm text-base-content/60">{{ $maintainer->displayName() }}</p>
                    </div>
                    <x-button
                        type="button"
                        class="btn-ghost btn-square btn-sm shrink-0 text-error"
                        wire:click="removeMaintainer({{ $maintainer->id }})"
                        wire:confirm="{{ __('ui.events.maintainers.remove_confirm') }}"
                        icon="o-trash"
                        :tooltip="__('ui.common.remove')"
                        :aria-label="__('ui.common.remove')"
                    />
                </li>
            @endforeach
        </ul>

        <div class="mt-4 space-y-3">
            @if ($selectedMaintainerOption)
                <div class="flex items-center gap-3 rounded-lg border border-base-300 bg-base-100 p-3">
                    <div class="avatar shrink-0">
                        <div class="h-10 w-10 overflow-hidden rounded-full">
                            <img
                                src="{{ $selectedMaintainerOption['avatar'] }}"
                                alt="{{ $selectedMaintainerOption['name'] }}"
                                class="h-full w-full object-cover"
                            />
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ $selectedMaintainerOption['name'] }}</p>
                        <p class="truncate text-sm text-base-content/60">{{ $selectedMaintainerOption['display_name'] }}</p>
                    </div>
                    <x-button
                        type="button"
                        class="btn-ghost btn-square btn-sm shrink-0"
                        wire:click="clearMaintainerCandidate"
                        icon="o-x-mark"
                        :tooltip="__('ui.common.remove')"
                        :aria-label="__('ui.common.remove')"
                    />
                </div>
                <x-button
                    type="button"
                    class="btn-primary btn-sm"
                    wire:click="addMaintainer"
                    data-ui="event-maintainer-add"
                >
                    {{ __('ui.events.maintainers.add_action') }}
                </x-button>
            @else
                <div
                    class="relative"
                    x-data="{
                        open: true,
                        activeIndex: -1,
                        optionIds: {{ Js::from(collect($userOptions)->pluck('id')->values()->all()) }},
                        navigateDown() {
                            if (this.optionIds.length === 0) {
                                return;
                            }
                            this.activeIndex = this.activeIndex < this.optionIds.length - 1
                                ? this.activeIndex + 1
                                : 0;
                            this.applyActiveStyles();
                        },
                        navigateUp() {
                            if (this.optionIds.length === 0) {
                                return;
                            }
                            this.activeIndex = this.activeIndex <= 0
                                ? this.optionIds.length - 1
                                : this.activeIndex - 1;
                            this.applyActiveStyles();
                        },
                        onEnter(event) {
                            event.preventDefault();
                            event.stopPropagation();
                            if (this.optionIds.length === 0) {
                                return;
                            }
                            const index = this.activeIndex >= 0 ? this.activeIndex : 0;
                            const userId = this.optionIds[index];
                            if (this.$wire.$island) {
                                this.$wire.$island('event-maintainers').selectMaintainerCandidate(userId);
                                return;
                            }
                            this.$wire.selectMaintainerCandidate(userId);
                        },
                        applyActiveStyles() {
                            this.$nextTick(() => {
                                const items = this.$refs.suggestions?.querySelectorAll('[data-maintainer-option-index]') ?? [];
                                items.forEach((element, index) => {
                                    const active = index === this.activeIndex;
                                    element.classList.toggle('bg-base-200', active);
                                    element.setAttribute('aria-selected', active ? 'true' : 'false');
                                });
                                items[this.activeIndex]?.scrollIntoView({ block: 'nearest' });
                            });
                        },
                        activeDescendantId() {
                            return this.activeIndex < 0 ? '' : 'event-maintainer-option-' + this.activeIndex;
                        },
                    }"
                    @focusin="open = true"
                    @keydown.arrow-down.prevent="navigateDown()"
                    @keydown.arrow-up.prevent="navigateUp()"
                    @keydown.enter.prevent.stop="onEnter($event)"
                    @keydown.escape="open = false; activeIndex = -1"
                >
                    <label class="label" for="event-maintainer-user-search">
                        <span class="label-text">{{ __('ui.user_requests.invite_user_search_label') }}</span>
                    </label>
                    <input
                        id="event-maintainer-user-search"
                        type="text"
                        wire:model.live.debounce.300ms="lastSearchTerm"
                        placeholder="{{ __('ui.user_requests.invite_user_search_placeholder') }}"
                        autocomplete="off"
                        class="input input-bordered w-full"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-controls="event-maintainer-suggestions"
                        x-bind:aria-expanded="open && optionIds.length > 0 ? 'true' : 'false'"
                        x-bind:aria-activedescendant="activeDescendantId()"
                    />

                    @if (mb_strlen($lastSearchTerm) >= 2)
                        <ul
                            x-ref="suggestions"
                            id="event-maintainer-suggestions"
                            class="absolute left-0 right-0 z-20 mt-1 max-h-56 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                            data-ui="event-maintainer-suggestions"
                            role="listbox"
                        >
                            <li
                                wire:loading
                                wire:target="lastSearchTerm,searchMaintainerCandidates,updatedLastSearchTerm"
                                class="px-3 py-2 text-sm text-base-content/60"
                            >
                                {{ __('ui.common.loading') }}
                            </li>
                            @forelse ($userOptions as $option)
                                <li wire:key="event-maintainer-option-{{ $option['id'] }}">
                                    <button
                                        type="button"
                                        wire:click="selectMaintainerCandidate({{ $option['id'] }})"
                                        id="event-maintainer-option-{{ $loop->index }}"
                                        data-maintainer-option-index="{{ $loop->index }}"
                                        class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-base-200"
                                        role="option"
                                        aria-selected="false"
                                    >
                                        <div class="avatar shrink-0">
                                            <div class="h-8 w-8 overflow-hidden rounded-full">
                                                <img src="{{ $option['avatar'] }}" alt="" class="h-full w-full object-cover" />
                                            </div>
                                        </div>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium">{{ $option['name'] }}</span>
                                            <span class="block truncate text-xs text-base-content/60">{{ $option['display_name'] }}</span>
                                        </span>
                                    </button>
                                </li>
                            @empty
                                <li
                                    wire:loading.remove
                                    wire:target="lastSearchTerm,searchMaintainerCandidates,updatedLastSearchTerm"
                                    class="px-3 py-2 text-sm text-base-content/60"
                                >
                                    {{ __('ui.user_requests.invite_user_no_results') }}
                                </li>
                            @endforelse
                        </ul>
                    @endif
                </div>
            @endif

            <x-field-error :messages="$errors->get('maintainer')" class="mt-2" />
        </div>
    </div>
@endif
